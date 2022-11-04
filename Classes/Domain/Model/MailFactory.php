<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailFactory
{
    private int $storageFolder;
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
            $uid
        );
    }

    /**
     * @param int $storageFolder
     */
    public function __construct(int $storageFolder)
    {
        $this->storageFolder = $storageFolder;
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($storageFolder)['mod.']['web_modules.']['mail.'] ?? [];
    }

    /**
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @return Mail|null
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function fromInternalPage(int $pageUid, int $sysLanguageUid = 0): ?Mail
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

        if ($sysLanguageUid > 0) {
            $mail->setSysLanguageUid($sysLanguageUid);
            // todo is this working with new route handling?
            $langParam = $this->pageTSConfiguration['langParams.'][$sysLanguageUid] ?? '&L=' . $sysLanguageUid;
            $mail->setPlainParams($mail->getPlainParams() . $langParam);
            $mail->setHtmlParams($mail->getHtmlParams() . $langParam);
            // get page title from translated page
            $pageRecordOverlay = $pageRepository->getPageOverlay($pageRecord, $sysLanguageUid);
            if ($pageRecordOverlay) {
                $mail->setSubject($pageRecordOverlay['title']);
            }
        }

        $htmlContent = '';
        $htmlLinks = [];
        if ($mail->isHtml()) {
            $htmlUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getHtmlParams());
            $htmlContent = $this->fetchHtmlContent($htmlUrl);
            $htmlLinks = MailerUtility::extractHyperLinks($htmlContent, $htmlUrl);
            if ($htmlLinks) {
                $htmlContent = $this->replaceHyperLinks($htmlContent, $htmlLinks, BackendDataUtility::getBaseUrl($mail->getPage()));
            }
        }

        $plainTextContent = '';
        if ($mail->isPlain()) {
            $plainTextUrl = BackendDataUtility::getUrlForInternalPage($mail->getPage(), $mail->getPlainParams());
            $plainTextContent = $this->fetchPlainTextContent($plainTextUrl);
        }

        $mailParts = [
            'messageid' => MailerUtility::generateMessageId(),
            'plain' => [
                'content' => $plainTextContent
            ],
            'html' => [
                'content' => $htmlContent,
                'hrefs' => $htmlLinks,
            ],
        ];
        $mailContent = base64_encode(serialize($mailParts));

        $mail->setMailContent($mailContent);
        $mail->setRenderedSize(strlen($mailContent));

        return $mail;
    }

    /**
     * @param string $subject
     * @param string $htmlContentUrl
     * @param string $plainContentUrl
     * @return Mail|null
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
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
            $htmlContent = $this->fetchHtmlContent($htmlUrl);
            $matches = [];
            $res = preg_match('/<meta\s+http-equiv="Content-Type"\s+content="text\/html;\s+charset=([^"]+)"/m', $htmlContent, $matches);
            if ($res === 1) {
                $mail->setCharset($matches[1]);
            }
        }

        // validate plain text content url syntax
        $plainTextContent = '';
        $urlParts = @parse_url($plainContentUrl);
        if (!$plainContentUrl || $urlParts === false || !$urlParts['host']) {
            $mail->setPlainParams('');
            // deactivate plain text mail sending option
            $mail->removePlainSendOption();
        } else {
            $plainTextUrl = MailerUtility::getUrlForExternalPage($mail->getHtmlParams());
            $plainTextContent = $this->fetchPlainTextContent($plainTextUrl);
        }

        if ($mail->getSendOptions()->isNone()) {
            return null;
        }

        $mailParts = [
            'messageid' => MailerUtility::generateMessageId(),
            'plain' => [
                'content' => $plainTextContent
            ],
            'html' => [
                'content' => $htmlContent
            ],
        ];
        $mailContent = base64_encode(serialize($mailParts));

        $mail->setMailContent($mailContent);
        $mail->setRenderedSize(strlen($mailContent));

        return $mail;
    }

    /**
     * @param string $subject
     * @param string $message
     * @param string $senderName
     * @param string $senderEmail
     * @param bool $breakLines
     * @return Mail|null
     */
    public function fromText(string $subject, string $message, string $senderName, string $senderEmail, bool $breakLines = true): ?Mail
    {
        if (!trim($message)) {
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

        $message = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_-->' . $message . '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';
        if ($mail->isRedirect()) {
            $message = MailerUtility::shortUrlsInPlainText(
                $message,
                $mail->isRedirectAll() ? 0 : 76,
                BackendDataUtility::getBaseUrl($this->storageFolder)
            );
        }
        if ($breakLines) {
            $message = wordwrap($message, 76);
        }

        $mailParts = [
            'messageid' => MailerUtility::generateMessageId(),
            'plain' => [
                'content' => $message
            ],
            'html' => [
                'content' => ''
            ]
        ];
        $mailContent = base64_encode(serialize($mailParts));

        $mail->setMailContent($mailContent);
        $mail->setRenderedSize(strlen($mailContent));

        return $mail;
    }

    /**
     * @return Mail
     */
    protected function newMailFromPageTSConfiguration(): Mail
    {
        $mail = new Mail();
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
            ->setFlowedFormat((bool)($this->pageTSConfiguration['flowed_format'] ?? false))
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
            if ($htmlContent === false) {
                ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_html_uri_is_invalid'),
                    LanguageUtility::getLL('dmail_error'));
                return false;
            } else {
                if (MailerUtility::contentContainsFrameTag($htmlContent)) {
                    ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_frames_not allowed'), LanguageUtility::getLL('dmail_error'));
                    return false;
                }
                if (!MailerUtility::contentContainsBoundaries($htmlContent)) {
                    ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_no_html_boundaries'),
                        LanguageUtility::getLL('dmail_warning'));
                }

                return $htmlContent;
            }
        } catch (RequestException $exception) {
            ViewUtility::addErrorToFlashMessageQueue(' Requested URL: ' . $htmlContentUrlWithUsernameAndPassword . ' Reason: ' . $exception->getResponse()->getReasonPhrase(),
                LanguageUtility::getLL('dmail_no_html_content'));
            return false;
        }
    }

    protected function replaceHyperLinks(string $htmlContent, array $hyperLinks, string $baseUrl): string
    {
        $glue = str_contains($baseUrl, '?') ? '&' : '?';
        $jumpUrlPrefix = '';
        $jumpUrlUseId = false;
        if ($this->pageTSConfiguration['enable_jump_url'] ?? false) {
            $jumpUrlPrefix = $baseUrl . $glue .
                'mail=###SYS_MAIL_ID###' .
                (($this->pageTSConfiguration['jumpurl_tracking_privacy'] ?? false) ? '' : '&rid=###SYS_TABLE_NAME###-###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl=';
            $jumpUrlUseId = true;
        }
        $jumpUrlUseMailto = (bool)($this->pageTSConfiguration['enable_mailto_jump_url'] ?? false);

        return MailerUtility::replaceHrefsInContent($htmlContent, $hyperLinks, $jumpUrlPrefix, $jumpUrlUseId, $jumpUrlUseMailto);
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
                ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_plain_uri_is_invalid'),
                    LanguageUtility::getLL('dmail_error'));
                return false;
            } else {
                if (!MailerUtility::contentContainsBoundaries($plainContent)) {
                    ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_no_plain_boundaries'),
                        LanguageUtility::getLL('dmail_warning'));
                }
                return $plainContent;
            }
        } catch (RequestException|ConnectException $exception) {
            ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_no_plain_content') . ' Requested URL: ' . $plainContentUrlWithUserNameAndPassword . ' Reason: ' . $exception->getResponse()->getReasonPhrase(),
                LanguageUtility::getLL('dmail_error'));
            return false;
        }
    }
}
