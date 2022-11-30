<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Exception\HtmlContentFetchFailedException;
use MEDIAESSENZ\Mail\Exception\PlainTextContentFetchFailedException;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use pQuery;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailFactory
{
    private SiteInterface $site;

    /**
     * @var array|mixed
     */
    private array $pageTSConfiguration = [];

    /**
     * @param $uid
     * @return $this
     */
    public static function forStorageFolder($uid): self
    {
        return GeneralUtility::makeInstance(
            static::class,
            $uid,
            GeneralUtility::makeInstance(Context::class),
            GeneralUtility::makeInstance(SiteFinder::class)
        );
    }

    /**
     * @param int $storageFolder
     * @param Context $context
     * @param SiteFinder $siteFinder
     * @throws SiteNotFoundException
     */
    public function __construct(private readonly int $storageFolder, private readonly Context $context, private readonly SiteFinder $siteFinder)
    {
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($storageFolder)['mod.']['web_modules.']['mail.'] ?? [];
        $this->site = $this->siteFinder->getSiteByPageId($storageFolder);
    }

    /**
     * @param int $pageUid
     * @param int $languageUid
     * @return Mail|null
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function fromInternalPage(int $pageUid, int $languageUid = 0): ?Mail
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageRecord = $pageRepository->getPage($pageUid);
        if (!$pageRecord) {
            return null;
        }
        $mail = $this->newMailFromPageTSConfiguration();
        $mail->setType(MailType::INTERNAL)
            ->setPage($pageRecord['uid'])
            ->setSubject($pageRecord['title'] ?? '')
            ->setRedirectUrl(BackendDataUtility::getBaseUrl($pageUid))
            ->setPlainParams($this->pageTSConfiguration['plainParams'] ?? '')
            ->setHtmlParams($this->pageTSConfiguration['HTMLParams'] ?? '')
            ->setEncoding($this->pageTSConfiguration['direct_mail_encoding'] ?? 'quoted-printable')
            ->setCharset($this->pageTSConfiguration['direct_mail_charset'] ?? 'iso-8859-1');

        if ($languageUid > 0) {
            $mail->setSysLanguageUid($languageUid);
            // todo is this working with new route handling?
            $langParam = $this->pageTSConfiguration['langParams.'][$languageUid] ?? '&L=' . $languageUid;
            $mail->setPlainParams($mail->getPlainParams() . $langParam);
            $mail->setHtmlParams($mail->getHtmlParams() . $langParam);
            // get page title from translated page
            $pageRecordOverlay = $pageRepository->getPageOverlay($pageRecord, $languageUid);
            if ($pageRecordOverlay) {
                $mail->setSubject($pageRecordOverlay['title']);
            }
        }

        $htmlLinks = [];
        if ($mail->isHtml()) {
            $htmlUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getHtmlParams());
            $htmlContent = $this->fetchHtmlContent($htmlUrl);

            $baseUrl = BackendDataUtility::getBaseUrl($mail->getPage(), $languageUid);
            $glue = str_contains($baseUrl, '?') ? '&' : '?';
            $enableJumpUrl = (bool)($this->pageTSConfiguration['enable_jump_url'] ?? false);
            $enableMailToJumpUrl = (bool)($this->pageTSConfiguration['enable_mailto_jump_url'] ?? false);
            $jumpUrlTrackingPrivacy = (bool)($this->pageTSConfiguration['jumpurl_tracking_privacy'] ?? false);
            $jumpUrlPrefix = $baseUrl . $glue .
                'mail=###SYS_MAIL_ID###' .
                ($jumpUrlTrackingPrivacy ? '' : '&rid=###SYS_TABLE_NAME###-###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl=';

            $dom = pQuery::parseStr($htmlContent);
            /** @var pQuery\IQuery $element */
            foreach($dom->query('a,form,area') as $element) {
                $hyperLinkAttribute = match ($element->tagName()) {
                    'form' => 'action',
                    default => 'href',
                };
                $originalHyperLink = $element->attr($hyperLinkAttribute);
                if (!str_starts_with(trim($originalHyperLink), '#')) {
                    $absoluteHyperlink = MailerUtility::absRef($originalHyperLink, $baseUrl);
                    if ($enableJumpUrl && !$element->attr('no_jumpurl') && (!str_starts_with($originalHyperLink, 'mailto:') || $enableMailToJumpUrl)) {
                        $hyperLink = array_search($originalHyperLink, array_column($htmlLinks, 'ref'));
                        if ($hyperLink === false) {
                            $htmlLinks[] = [
                                'tag' => $element->tagName(),
                                'ref' => $originalHyperLink,
                                'absRef' => $absoluteHyperlink,
                                'title' => $element->attr('title') ?: $originalHyperLink,
                            ];
                            end($htmlLinks);
                            $hyperLink = key($htmlLinks);
                        }
                        $absoluteHyperlink = $jumpUrlPrefix . (string)$hyperLink;
                    }
                    $element->attr($hyperLinkAttribute, $absoluteHyperlink);
                }
            }

            $mail->setHtmlContent($dom->html());
            $mail->setHtmlLinks($htmlLinks);
        }

        if ($mail->isPlain()) {
            $plainTextUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getPlainParams());
            $mail->setPlainContent($this->fetchPlainTextContent($plainTextUrl));
        }

        $mail->setMessageId(MailerUtility::generateMessageId());

        return $mail;
    }

    /**
     * @param string $subject
     * @param string $htmlContentUrl
     * @param string $plainContentUrl
     * @return Mail|null
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws HtmlContentFetchFailedException
     * @throws PlainTextContentFetchFailedException
     */
    public function fromExternalUrls(string $subject, string $htmlContentUrl = '', string $plainContentUrl = ''): ?Mail
    {
        $mail = $this->newMailFromPageTSConfiguration();
        $mail->setType(MailType::EXTERNAL)
            ->setSubject($subject)
            ->setHtmlParams($htmlContentUrl)
            ->setPlainParams($plainContentUrl);

        // validate html content url syntax
        $htmlContent = '';
        $urlParts = @parse_url($htmlContentUrl);
        if (!$htmlContentUrl || $urlParts === false || !$urlParts['host']) {
            $mail->setHtmlParams('');
            // deactivate html mail sending option
            $mail->removeHtmlSendOption();
        } else {
            $htmlUrl = MailerUtility::getUrlForExternalPage($mail->getHtmlParams());
            // todo only add domain with scheme
            $mail->setRedirectUrl($htmlUrl);
            $htmlContent = $this->fetchHtmlContent($htmlUrl);
            if ($htmlContent === false) {
                throw new HtmlContentFetchFailedException;
            }
            $matches = [];
            $res = preg_match('/<meta\s+http-equiv="Content-Type"\s+content="text\/html;\s+charset=([^"]+)"/m', $htmlContent, $matches);
            if ($res === 1) {
                $mail->setCharset($matches[1]);
            }
        }

        // validate plain text content url syntax
        $plainContent = '';
        $urlParts = @parse_url($plainContentUrl);
        if (!$plainContentUrl || $urlParts === false || !$urlParts['host']) {
            $mail->setPlainParams('');
            // deactivate plain text mail sending option
            $mail->removePlainSendOption();
        } else {
            $plainTextUrl = MailerUtility::getUrlForExternalPage($mail->getHtmlParams());
            $plainContent = $this->fetchPlainTextContent($plainTextUrl);
            if ($plainContent === false) {
                throw new PlainTextContentFetchFailedException;
            }
        }

        if ($mail->getSendOptions()->isNone()) {
            return null;
        }

        $mail->setMessageId(MailerUtility::generateMessageId());
        $mail->setPlainContent($plainContent);
        $mail->setHtmlContent($htmlContent);

        return $mail;
    }

    /**
     * @param string $subject
     * @param string $plainContent
     * @param string $senderName
     * @param string $senderEmail
     * @param bool $breakLines
     * @return Mail|null
     */
    public function fromText(string $subject, string $plainContent, string $senderName, string $senderEmail, bool $breakLines = true): ?Mail
    {
        if (!trim($plainContent)) {
            return null;
        }

        $mail = $this->newMailFromPageTSConfiguration();
        $mail->setType(MailType::EXTERNAL)
            ->setFromName($senderName)
            ->setFromEmail($senderEmail)
            ->setSubject($subject)
            ->setSendOptions(new SendFormat(SendFormat::PLAIN))
            ->setEncoding($this->pageTSConfiguration['quick_mail_encoding'] ?? 'quoted-printable')
            ->setCharset($this->pageTSConfiguration['quick_mail_charset'] ?? 'utf-8');

        $plainContent = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_-->' . $plainContent . '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';
        // shorten urls is done in mailer service sendPersonalizedMail method as well, but is necessary here as well, to not break links due to following wordwrap
        if ($mail->isRedirect()) {
            $plainContent = MailerUtility::shortUrlsInPlainText(
                $plainContent,
                $mail->isRedirectAll() ? 0 : 76,
                BackendDataUtility::getBaseUrl($this->storageFolder),
                $this->site->getLanguageById($mail->getSysLanguageUid())->getBase()->getHost() ?: '*'
            );
        }
        if ($breakLines) {
            $plainContent = wordwrap($plainContent, 76);
        }

        $mail->setMessageId(MailerUtility::generateMessageId());
        $mail->setPlainContent($plainContent);

        return $mail;
    }

    /**
     * @return Mail
     */
    protected function newMailFromPageTSConfiguration(): Mail
    {
        $mail = GeneralUtility::makeInstance(Mail::class);
        $mail
            ->setFromEmail($this->pageTSConfiguration['from_email'] ?? '')
            ->setFromName($this->pageTSConfiguration['from_name'] ?? '')
            ->setReplyToEmail($this->pageTSConfiguration['replyto_email'] ?? '')
            ->setReplyToName($this->pageTSConfiguration['replyto_name'] ?? '')
            ->setReturnPath($this->pageTSConfiguration['return_path'] ?? '')
            ->setPriority((int)($this->pageTSConfiguration['priority'] ?? 3))
            ->setRedirect((bool)($this->pageTSConfiguration['redirect'] ?? false))
            ->setRedirectAll((bool)($this->pageTSConfiguration['redirect_all'] ?? false))
            ->setOrganisation($this->pageTSConfiguration['organisation'] ?? '')
            ->setAuthCodeFields($this->pageTSConfiguration['auth_code_fields'] ?? '')
            ->setSendOptions(new SendFormat($this->pageTSConfiguration['sendOptions'] ?? $GLOBALS['TCA']['tx_mail_domain_model_mail']['columns']['send_options']['config']['default']))
            ->setIncludeMedia((bool)($this->pageTSConfiguration['includeMedia'] ?? false))
            ->setPid($this->storageFolder);

        return $mail;
    }

    /**
     * @param string $htmlUrl
     * @return bool|string
     */
    protected function fetchHtmlContent(string $htmlUrl): bool|string
    {
        $htmlContentUrlWithUsernameAndPassword = MailerUtility::addUsernameAndPasswordToUrl($htmlUrl, $this->pageTSConfiguration);
        try {
            $htmlContent = MailerUtility::fetchContentFromUrl($htmlContentUrlWithUsernameAndPassword);
            if ($htmlContent === false || MailerUtility::contentContainsFrameTag($htmlContent)) {
                return false;
            } else {
                if (!MailerUtility::contentContainsBoundaries($htmlContent)) {
                    ViewUtility::addNotificationWarning(LanguageUtility::getLL('mail.wizard.notification.noHtmlBoundariesFound.message'),
                        LanguageUtility::getLL('general.notification.severity.warning.title'));
                }

                return $htmlContent;
            }
        } catch (RequestException|ConnectException) {
            return false;
        }
    }

    /**
     * @param string $plainTextUrl
     * @return bool|string
     */
    protected function fetchPlainTextContent(string $plainTextUrl): bool|string
    {
        $plainContentUrlWithUserNameAndPassword = MailerUtility::addUsernameAndPasswordToUrl($plainTextUrl, $this->pageTSConfiguration);
        try {
            $plainContent = MailerUtility::fetchContentFromUrl($plainContentUrlWithUserNameAndPassword);
            if ($plainContent === false) {
                return false;
            } else {
                if (!MailerUtility::contentContainsBoundaries($plainContent)) {
                    ViewUtility::addNotificationWarning(LanguageUtility::getLL('mail.wizard.notification.noPlainTextBoundariesFound.message'),
                        LanguageUtility::getLL('general.notification.severity.warning.title'));
                }
                return $plainContent;
            }
        } catch (RequestException|ConnectException) {
            return false;
        }
    }
}
