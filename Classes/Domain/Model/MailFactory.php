<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use DOMElement;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Masterminds\HTML5;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Exception\HtmlContentFetchFailedException;
use MEDIAESSENZ\Mail\Exception\PlainTextContentFetchFailedException;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Symfony\Component\CssSelector\Exception\ParseException;
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
    public function __construct(private int $storageFolder, private Context $context, private SiteFinder $siteFinder)
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
        $pageRecord = $pageRepository->getPage($pageUid, true);
        if (!$pageRecord) {
            return null;
        }
        $mail = $this->newMailFromPageTSConfiguration();
        $mail->setType(MailType::INTERNAL)
            ->setStep(1)
            ->setPage($pageRecord['uid'])
            ->setSubject($pageRecord['title'] ?? '')
            ->setRedirectUrl(BackendDataUtility::getBaseUrl($pageUid))
            ->setPlainParams($this->pageTSConfiguration['plainParams'] ?? '')
            ->setHtmlParams($this->pageTSConfiguration['htmlParams'] ?? '')
            ->setEncoding($this->pageTSConfiguration['encoding'] ?? 'quoted-printable')
            ->setCharset($this->pageTSConfiguration['charset'] ?? 'iso-8859-1');

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
            $htmlUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getHtmlParams(), (int)($this->pageTSConfiguration['simulateUsergroup'] ?? 0));
            $htmlContent = $this->fetchHtmlContent($htmlUrl);
            if ($htmlContent !== false) {
                $baseUrl = BackendDataUtility::getBaseUrl($mail->getPage(), $languageUid);
                $glue = str_contains($baseUrl, '?') ? '&' : '?';
                $clickTracking = (bool)($this->pageTSConfiguration['clickTracking'] ?? false);
                $clickTrackingMailTo = (bool)($this->pageTSConfiguration['clickTrackingMailTo'] ?? false);
                $trackingPrivacy = (bool)($this->pageTSConfiguration['trackingPrivacy'] ?? false);
                $jumpUrlPrefix = $baseUrl . $glue .
                    'mail=###MAIL_ID###' .
                    ($trackingPrivacy ? '' : '&rid=###MAIL_RECIPIENT_SOURCE###-###USER_uid###') .
                    '&aC=###MAIL_AUTHCODE###' .
                    '&jumpurl=';

                $html = new HTML5();
                $domDocument = $html->loadHTML($htmlContent);
                foreach (['a', 'form', 'area'] as $tag) {
                    $domElements = $domDocument->getElementsByTagName($tag);
                    /** @var DOMElement $element */
                    foreach ($domElements as $domElement) {
                        $hyperLinkAttribute = match ($domElement->tagName) {
                            'form' => 'action',
                            default => 'href',
                        };
                        $originalHyperLink = $domElement->getAttribute('href');
                        if (!str_starts_with(trim($originalHyperLink), '#')) {
                            $absoluteHyperlink = MailerUtility::absRef($originalHyperLink, $baseUrl);
                            if ($clickTracking && !$domElement->getAttribute('data-do-not-track') && (!str_starts_with($originalHyperLink, 'mailto:') || $clickTrackingMailTo)) {
                                $hyperLink = array_search($originalHyperLink, array_column($htmlLinks, 'ref'));
                                if ($hyperLink === false) {
                                    $htmlLinks[] = [
                                        'tag' => $hyperLinkAttribute,
                                        'ref' => $originalHyperLink,
                                        'absRef' => $absoluteHyperlink,
                                        'title' => $domElement->getAttribute('title') ?: $originalHyperLink,
                                    ];
                                    end($htmlLinks);
                                    $hyperLink = key($htmlLinks);
                                }
                                $absoluteHyperlink = $jumpUrlPrefix . $hyperLink;
                            }
                            $domElement->setAttribute($hyperLinkAttribute, $absoluteHyperlink);
                        }
                    }
                }

                if ($clickTracking) {
                    // Tracking pixel
                    $images = $domDocument->getElementsByTagName('img');
                    /** @var DOMElement $image */
                    foreach ($images as $image) {
                        if ($image->hasAttribute('data-mailer-ping')) {
                            $htmlLinks[] = [
                                'tag' => 'src',
                                'ref' => $image->getAttribute('src'),
                                'absRef' => MailerUtility::absRef($image->getAttribute('src'), $baseUrl)
                            ];
                            $image->setAttribute('src', $jumpUrlPrefix . $image->getAttribute('src'));
                            // Replace only first image src with jumpurl
                            break;
                        }
                    }
                }

                $mail->setHtmlContent($html->saveHTML($domDocument));
                $mail->setHtmlLinks($htmlLinks);
            }
        }

        if ($mail->isPlain()) {
            $plainTextUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getPlainParams(), (int)($this->pageTSConfiguration['simulateUsergroup'] ?? 0));
            $plainContent = $this->fetchPlainTextContent($plainTextUrl);
            if ($plainContent !== false) {
                $mail->setPlainContent($plainContent);
            }
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
     * @throws ParseException
     */
    public function fromExternalUrls(string $subject, string $htmlContentUrl = '', string $plainContentUrl = ''): ?Mail
    {
        $mail = $this->newMailFromPageTSConfiguration();
        $mail->setType(MailType::EXTERNAL)
            ->setStep(1)
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

            $htmlContent = MailerUtility::makeImageSourcesAbsolute($htmlContent, $htmlUrl);
            $htmlContent = MailerUtility::addInlineStyles($htmlContent, $htmlUrl);
            $htmlContent = MailerUtility::removeTags($htmlContent, ['style', 'link']);
            $htmlContent = MailerUtility::removeClassAttributes($htmlContent);

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
            ->setStep(1)
            ->setFromName($senderName)
            ->setFromEmail($senderEmail)
            ->setSubject($subject)
            ->setSendOptions(new SendFormat(SendFormat::PLAIN))
            ->setEncoding($this->pageTSConfiguration['quickMailEncoding'] ?? 'quoted-printable')
            ->setCharset($this->pageTSConfiguration['quickMailCharset'] ?? 'utf-8');

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
            ->setFromEmail($this->pageTSConfiguration['fromEmail'] ?? '')
            ->setFromName($this->pageTSConfiguration['fromName'] ?? '')
            ->setReplyToEmail($this->pageTSConfiguration['replyToEmail'] ?? '')
            ->setReplyToName($this->pageTSConfiguration['replyToName'] ?? '')
            ->setReturnPath($this->pageTSConfiguration['returnPath'] ?? '')
            ->setPriority((int)($this->pageTSConfiguration['priority'] ?? 3))
            ->setRedirect((bool)($this->pageTSConfiguration['redirect'] ?? false))
            ->setRedirectAll((bool)($this->pageTSConfiguration['redirectAll'] ?? false))
            ->setOrganisation($this->pageTSConfiguration['organisation'] ?? '')
            ->setAuthCodeFields($this->pageTSConfiguration['authCodeFields'] ?? '')
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

            // remove script tags
            $htmlContent = MailerUtility::removeTags((string)$htmlContent, ['script']);

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
