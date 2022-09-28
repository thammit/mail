<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Mail\MailMessage;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use PDO;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\MathUtility;

class MailerService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * @var array Used to store public variables
     */
    public array $dmailer = [];

    /*
     * special header to identify returned mail
     *
     * @var string
     */
    protected string $TYPO3MID = '';

    /*
     * @var array the mail parts (HTML and Plain, incl. href and link to media)
     */
    protected array $mailParts = [];
    protected int $sendPerCycle = 50;
    protected bool $isHtml = false;
    protected bool $isPlain = false;
    protected bool $includeMedia = false;
    protected bool $flowedFormat = false;
    protected string $backendUserLanguage = 'default';
    protected bool $isTestMail = false;
    protected string $charset = 'utf-8';
    protected string $messageId = '';
    protected string $subject = '';
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $organisation = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected int $priority = 3;
    protected string $authCodeFieldList = '';
    protected string $backendCharset = 'utf-8';
    protected string $message = '';
    protected bool $notificationJob = false;
    protected string $jumpUrlPrefix = '';
    protected bool $jumpUrlUseMailto = false;
    protected bool $jumpUrlUseId = false;
    protected string $siteIdentifier = '';

    public function __construct(
        protected CharsetConverter $charsetConverter,
        protected SysDmailMaillogRepository $sysDmailMaillogRepository
    ) {
    }

    /**
     * @return string
     */
    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }

    /**
     * @param string $siteIdentifier
     */
    public function setSiteIdentifier(string $siteIdentifier): void
    {
        $this->siteIdentifier = $siteIdentifier;
    }

    public function setMailPart($part, $value): void
    {
        $this->mailParts[$part] = $value;
    }

    /**
     * @return array
     */
    public function getMailParts(): array
    {
        return $this->mailParts;
    }

    /**
     * @param string $part
     * @return mixed
     */
    public function getMailPart(string $part): mixed
    {
        return $this->mailParts[$part] ?? '';
    }

    /**
     * @return string
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * @return bool
     */
    public function isTestMail(): bool
    {
        return $this->isTestMail;
    }

    /**
     * @param bool $isTestMail
     */
    public function setTestMail(bool $isTestMail): void
    {
        $this->isTestMail = $isTestMail;
    }

    public function getJumpUrlPrefix(): string
    {
        return $this->jumpUrlPrefix;
    }

    public function setJumpUrlPrefix(string $value): void
    {
        $this->jumpUrlPrefix = $value;
    }

    public function getJumpUrlUseId(): bool
    {
        return $this->jumpUrlUseId;
    }

    public function setJumpUrlUseId(bool $value): void
    {
        $this->jumpUrlUseId = $value;
    }

    public function getJumpUrlUseMailto(): bool
    {
        return $this->jumpUrlUseMailto;
    }

    public function setJumpUrlUseMailto(bool $value): void
    {
        $this->jumpUrlUseMailto = $value;
    }

    public function setIncludeMedia(bool $value): void
    {
        $this->includeMedia = $value;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }

    public function setPlainLinkIds($array): void
    {
        $this->mailParts['plain']['link_ids'] = $array;
    }

    public function setPlainContent(string $content): void
    {
        $this->mailParts['plain']['content'] = $content;
    }

    public function getPlainContent(): string
    {
        return $this->mailParts['plain']['content'] ?? '';
    }

    public function setHtmlContent(string $content): void
    {
        $this->mailParts['html']['content'] = $content;
    }

    public function getHtmlContent(): string
    {
        return $this->mailParts['html']['content'] ?? '';
    }

    public function setHtmlPath(string $path): void
    {
        $this->mailParts['html']['path'] = $path;
    }

    public function getHtmlPath(): string
    {
        return $this->mailParts['html']['path'] ?? '';
    }

    public function setHtmlHyperLinks(array $hrefs): void
    {
        $this->mailParts['html']['hrefs'] = $hrefs;
    }

    public function getHtmlHyperLinks(): array
    {
        return $this->mailParts['html']['hrefs'] ?? [];
    }

    /**
     * Adds plain-text, replaces the HTTP urls in the plain text and then encodes it
     *
     * @param string $content The plain text content
     *
     * @return void
     */
    public function addPlainContent(string $content): void
    {
        if ($this->jumpUrlPrefix) {
            [$content, $plainLinkIds] = MailerUtility::replaceUrlsInPlainText($content, $this->jumpUrlPrefix, $this->jumpUrlUseId);
            $this->setPlainLinkIds($plainLinkIds);
        }
        $this->setPlainContent($content);
    }

    /**
     * Initializing the MailMessage class and setting the first global variables. Write to log file if it's a cronjob
     *
     * @param int $sendPerCycle Total of recipient in a cycle
     * @param string $backendUserLanguage Language of the user
     *
     * @return void
     */
    public function start(int $sendPerCycle = 50, string $backendUserLanguage = 'en'): void
    {
        // Sets the message id
        $host = MailerUtility::getHostname();
        if (!$host || $host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
            $host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_',
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
        }

        $idLeft = time() . '.' . uniqid();
        $idRight = $host ?: 'symfony.generated';
        $this->messageId = $idLeft . '@' . $idRight;

        // Mailer engine parameters
        $this->sendPerCycle = $sendPerCycle;
        $this->backendUserLanguage = $backendUserLanguage;
    }

    /**
     * @param array $mailData
     * @param array $params
     * @return bool returns true if an error occurred
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function assemble(array $mailData, array $params): bool
    {
        $fetchPlainTextContent = ConfigurationUtility::shouldFetchPlainText($mailData);
        $fetchHtmlContent = ConfigurationUtility::shouldFetchHtml($mailData);

        if (!$fetchPlainTextContent && !$fetchHtmlContent) {
            ViewUtility::addInfoToFlashMessageQueue('', LanguageUtility::getLL('dmail_no_mail_content_format_selected'));
            return true;
        }

        $this->start();
        $this->setCharset($mailData['charset']);
        $this->setIncludeMedia((bool)$mailData['includeMedia']);

        $baseUrl = BackendDataUtility::getAbsoluteBaseUrlForMailPage((int)$mailData['page']);
        $glue = str_contains($baseUrl, '?') ? '&' : '?';
        if ($params['enable_jump_url'] ?? false) {
            $this->setJumpUrlPrefix($baseUrl . $glue .
                'mid=###SYS_MAIL_ID###' .
                ($params['jumpurl_tracking_privacy'] ? '' : '&rid=###SYS_TABLE_NAME###_###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl=');
            $this->setJumpUrlUseId(true);
        }
        if ($params['enable_mailto_jump_url'] ?? false) {
            $this->setJumpUrlUseMailto(true);
        }

        if ($fetchPlainTextContent) {
            $plainTextUrl = (int)$mailData['type'] === MailType::EXTERNAL ? MailerUtility::getUrlForExternalPage($mailData['plainParams']) : BackendDataUtility::getUrlForInternalPage($mailData['page'],
                $mailData['plainParams']);
            $plainContentUrlWithUserNameAndPassword = MailerUtility::addUsernameAndPasswordToUrl($plainTextUrl, $params);
            try {
                $plainContent = MailerUtility::fetchContentFromUrl($plainContentUrlWithUserNameAndPassword);
                if ($plainContent === false) {
                    ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_plain_uri_is_invalid'),
                        LanguageUtility::getLL('dmail_error'));
                    return true;
                } else {
                    if (!MailerUtility::contentContainsBoundaries($plainContent)) {
                        ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_no_plain_boundaries'),
                            LanguageUtility::getLL('dmail_warning'));
                    }
                    $this->setPlainContent($plainContent);
                }
            } catch (RequestException|ConnectException $exception) {
                ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_no_plain_content') . ' Requested URL: ' . $plainContentUrlWithUserNameAndPassword . ' Reason: ' . $exception->getResponse()->getReasonPhrase(),
                    LanguageUtility::getLL('dmail_error'));
                return true;
            }
        }

        if ($fetchHtmlContent) {
            $htmlUrl = (int)$mailData['type'] === MailType::EXTERNAL ? MailerUtility::getUrlForExternalPage($mailData['HTMLParams']) : BackendDataUtility::getUrlForInternalPage($mailData['page'],
                $mailData['HTMLParams']);
            $htmlContentUrlWithUsernameAndPassword = MailerUtility::addUsernameAndPasswordToUrl($htmlUrl, $params);
            try {
                $htmlContent = MailerUtility::fetchContentFromUrl($htmlContentUrlWithUsernameAndPassword);
                if ($htmlContent === false) {
                    ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_html_uri_is_invalid'),
                        LanguageUtility::getLL('dmail_error'));
                    return true;
                } else {
                    if ((int)$mailData['type'] == MailType::EXTERNAL) {
                        // Try to auto-detect the charset of the message
                        $matches = [];
                        $res = preg_match('/<meta\s+http-equiv="Content-Type"\s+content="text\/html;\s+charset=([^"]+)"/m',
                            $htmlContent, $matches);
                        if ($res === 1) {
                            $this->setCharset($matches[1]);
                        } else {
                            if (isset($params['direct_mail_charset'])) {
                                $this->setCharset($params['direct_mail_charset']);
                            } else {
                                $this->setCharset('iso-8859-1');
                            }
                        }
                    }
                    if (MailerUtility::contentContainsFrameTag($htmlContent)) {
                        ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_frames_not allowed'), LanguageUtility::getLL('dmail_error'));
                        return true;
                    }
                    if (!MailerUtility::contentContainsBoundaries($htmlContent)) {
                        ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_no_html_boundaries'),
                            LanguageUtility::getLL('dmail_warning'));
                    }
                    $htmlHyperLinks = MailerUtility::extractHyperLinks($htmlContent, $baseUrl);
                    if ($htmlHyperLinks) {
                        $this->setHtmlHyperLinks($htmlHyperLinks);
                        $htmlContent = MailerUtility::replaceHrefsInContent($htmlContent, $this->getHtmlHyperLinks(), $this->getJumpUrlPrefix(),
                            $this->getJumpUrlUseId(), $this->getJumpUrlUseMailto());
                    }
                    $this->setHtmlContent($htmlContent);
                }
            } catch (RequestException $exception) {
                ViewUtility::addErrorToFlashMessageQueue(' Requested URL: ' . $htmlContentUrlWithUsernameAndPassword . ' Reason: ' . $exception->getResponse()->getReasonPhrase(),
                    LanguageUtility::getLL('dmail_no_html_content'));
                return true;
            }
        }

        // Update the record:
        $this->setMailPart('messageid', $this->getMessageId());
        $mailContent = base64_encode(serialize($this->getMailParts()));

        $updateData = [
            'issent' => 0,
            'charset' => $this->getCharset(),
            'mailContent' => $mailContent,
            'renderedSize' => strlen($mailContent),
            'long_link_rdct_url' => $baseUrl,
        ];

        GeneralUtility::makeInstance(SysDmailRepository::class)->update((int)$mailData['uid'], $updateData);

        return false;
    }

    /**
     * Preparing the Email. Headers are set in global variables
     *
     * @param array $mailData Record from the sys_dmail table
     *
     * @return void
     */
    public function prepare(array $mailData): void
    {
        $mailUid = $mailData['uid'];
        if ($mailData['flowedFormat']) {
            $this->flowedFormat = true;
        }
        if ($mailData['charset']) {
            if ($mailData['type'] == 0) {
                $this->charset = 'utf-8';
            } else {
                $this->charset = $mailData['charset'];
            }
        }

        $this->mailParts = unserialize(base64_decode($mailData['mailContent']));
        $this->messageId = $this->mailParts['messageid'];

        $this->subject = $this->charsetConverter->conv($mailData['subject'], $this->backendCharset, $this->charset);

        $this->fromEmail = $mailData['from_email'];
        $this->fromName = ($mailData['from_name'] ? $this->charsetConverter->conv($mailData['from_name'], $this->backendCharset, $this->charset) : '');

        $this->replyToEmail = ($mailData['replyto_email'] ?: '');
        $this->replyToName = ($mailData['replyto_name'] ? $this->charsetConverter->conv($mailData['replyto_name'], $this->backendCharset, $this->charset) : '');

        $this->organisation = ($mailData['organisation'] ? $this->charsetConverter->conv($mailData['organisation'], $this->backendCharset,
            $this->charset) : '');

        $this->priority = MathUtility::forceIntegerInRange((int)$mailData['priority'], 1, 5);
        $this->authCodeFieldList = ($mailData['authcode_fieldList'] ?: 'uid');

        $this->dmailer['sectionBoundary'] = '<!--' . Constants::CONTENT_SECTION_BOUNDARY;
        $this->dmailer['html_content'] = $this->getHtmlContent() ?? '';
        $this->dmailer['plain_content'] = $this->getPlainContent() ?? '';
        $this->dmailer['messageID'] = $this->messageId;
        $this->dmailer['sys_dmail_uid'] = $mailUid;
        $this->dmailer['sys_dmail_rec'] = $mailData;

        $this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['html_content']);
        foreach ($this->dmailer['boundaryParts_html'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_html'][$bKey] = explode('-->', $bContent, 2);

            // Remove useless HTML comments
            if (substr($this->dmailer['boundaryParts_html'][$bKey][0], 1) == 'END') {
                $this->dmailer['boundaryParts_html'][$bKey][1] = MailerUtility::removeHtmlComments($this->dmailer['boundaryParts_html'][$bKey][1]);
            }

            // Now, analyzing which media files are used in this part of the mail:
            $mediaParts = explode('cid:part', $this->dmailer['boundaryParts_html'][$bKey][1]);
            next($mediaParts);
            if (!isset($this->dmailer['boundaryParts_html'][$bKey]['mediaList'])) {
                $this->dmailer['boundaryParts_html'][$bKey]['mediaList'] = '';
            }
            foreach ($mediaParts as $part) {
                $this->dmailer['boundaryParts_html'][$bKey]['mediaList'] .= ',' . strtok($part, '.');
            }
        }
        $this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['plain_content']);
        foreach ($this->dmailer['boundaryParts_plain'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_plain'][$bKey] = explode('-->', $bContent, 2);
        }

        $this->isHtml = (bool)($this->getHtmlContent() ?? false);
        $this->isPlain = (bool)($this->getPlainContent() ?? false);
        $this->includeMedia = (bool)$mailData['includeMedia'];
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param string $content The HTML or plaintext part
     * @param array $recipient Recipient's data array
     * @param array $markers Existing markers that are mail-specific, not user-specific
     *
     * @return string Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function replaceMailMarkers(string $content, array $recipient, array $markers): string
    {
        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipFields'), true);
        if ($addRecipientFields = ConfigurationUtility::getExtensionConfiguration('addRecipFields')) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $addRecipientFields), true);
        }

        foreach ($rowFieldsArray as $substField) {
            if (isset($recipient[$substField])) {
                $markers['###USER_' . $substField . '###'] = $this->charsetConverter->conv($recipient[$substField], $this->backendCharset, $this->charset);
            }
        }

        // uppercase fields with uppercased values
        $uppercaseFieldsArray = ['name', 'firstname'];
        foreach ($uppercaseFieldsArray as $substField) {
            if (isset($recipient[$substField])) {
                $markers['###USER_' . strtoupper($substField) . '###'] = strtoupper($this->charsetConverter->conv($recipient[$substField],
                    $this->backendCharset, $this->charset));
            }
        }

        // Hook allows to manipulate the markers to add salutation etc.
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'])) {
            $mailMarkersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'];
            if (is_array($mailMarkersHook)) {
                $hookParameters = [
                    'row' => &$recipient,
                    'markers' => &$markers,
                ];
                $hookReference = &$this;
                foreach ($mailMarkersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        return GeneralUtility::makeInstance(MarkerBasedTemplateService::class)->substituteMarkerArray($content, $markers);
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param string $addressList list of recipient address, comma list of emails
     *
     * @return void
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function sendSimpleMail(string $addressList): void
    {
        $plainContent = '';
        if ($this->getPlainContent() ?? false) {
            [$contentParts] = MailerUtility::getBoundaryParts($this->dmailer['boundaryParts_plain']);
            $plainContent = implode('', $contentParts);
        }
        $this->setPlainContent($plainContent);

        $htmlContent = '';
        if ($this->getHtmlContent() ?? false) {
            [$contentParts] = MailerUtility::getBoundaryParts($this->dmailer['boundaryParts_html']);
            $htmlContent = implode('', $contentParts);
        }
        $this->setHtmlContent($htmlContent);

        $recipients = explode(',', $addressList);

        foreach ($recipients as $recipient) {
            $this->sendMailToRecipient($recipient);
        }
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param array $recipientData Recipient's data array
     * @param string $tableNameChar Table name, from which the recipient come from
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function sendPersonalizedMail(array $recipientData, string $tableNameChar): int
    {
        $returnCode = 0;

        foreach ($recipientData as $key => $value) {
            $recipientData[$key] = is_string($value) ? htmlspecialchars($value) : $value;
        }

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipientData['email'] = trim($recipientData['email']);

        if ($recipientData['email']) {
            $midRidId = 'MID' . $this->dmailer['sys_dmail_uid'] . '_' . $tableNameChar . $recipientData['uid'];
            $uniqMsgId = md5(microtime()) . '_' . $midRidId;
            $authCode = RecipientUtility::stdAuthCode($recipientData, $this->authCodeFieldList);

            $additionalMarkers = [
                // Put in the tablename of the userinformation
                '###SYS_TABLE_NAME###' => $tableNameChar,
                // Put in the uid of the mail-record
                '###SYS_MAIL_ID###' => $this->dmailer['sys_dmail_uid'],
                '###SYS_AUTHCODE###' => $authCode,
                // Put in the unique message id in HTML-code
                $this->dmailer['messageID'] => $uniqMsgId,
            ];

            $this->setHtmlContent('');
            if ($this->isHtml && ($recipientData['module_sys_dmail_html'] || $tableNameChar == 'P')) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->dmailer['boundaryParts_html'],
                    $recipientData['sys_dmail_categories_list']);
                $tempContent_HTML = implode('', $contentParts);

                if ($mailHasContent) {
                    $tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipientData, $additionalMarkers);
                    $this->setHtmlContent($tempContent_HTML);
                    $returnCode |= 1;
                }
            }

            // Plain
            $this->setPlainContent('');
            if ($this->isPlain) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->dmailer['boundaryParts_plain'],
                    $recipientData['sys_dmail_categories_list']);
                $plainTextContent = implode('', $contentParts);

                if ($mailHasContent) {
                    $plainTextContent = $this->replaceMailMarkers($plainTextContent, $recipientData, $additionalMarkers);
                    if ($this->dmailer['sys_dmail_rec']['use_rdct'] || $this->dmailer['sys_dmail_rec']['long_link_mode']) {
                        $plainTextContent = MailerUtility::shortUrlsInPlainText(
                            $plainTextContent,
                            $this->dmailer['sys_dmail_rec']['long_link_mode'] ? 0 : 76,
                            $this->dmailer['sys_dmail_rec']['long_link_rdct_url']
                        );
                    }
                    $this->setPlainContent($plainTextContent);
                    $returnCode |= 2;
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->dmailer['sys_dmail_rec']['return_path'] = str_replace('###XID###', $midRidId, $this->dmailer['sys_dmail_rec']['return_path']);

            if ($returnCode && GeneralUtility::validEmail($recipientData['email'])) {
                $this->sendMailToRecipient(
                    new Address($recipientData['email'], $this->charsetConverter->conv($recipientData['name'], $this->backendCharset, $this->charset)),
                    $recipientData
                );
            }

        }

        return $returnCode;
    }

    /**
     * Mass send to recipient in the list
     *
     * @param array $groupedRecipientIds List of recipients' ID in the sys_dmail table
     * @param int $mailUid Directmail ID. UID of the sys_dmail table
     * @return boolean
     * @throws DBALException
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function massSend(array $groupedRecipientIds, int $mailUid): bool
    {
        $numberOfSentMails = 0;
        $finished = true;
        foreach ($groupedRecipientIds as $table => $listArr) {
            if (is_array($listArr)) {
                $numberOfSentMailsOfGroup = 0;
                // Find tKey
                $recipientTable = match ($table) {
                    'tt_address', 'fe_users' => substr($table, 0, 1),
                    'PLAINLIST' => 'P',
                    default => 'u',
                };

                // get already sent mails
                $sentMails = $this->sysDmailMaillogRepository->findSentMails($mailUid, $recipientTable);
                if ($table === 'PLAINLIST') {
                    foreach ($listArr as $kval => $recipientData) {
                        $kval++;
                        if (!in_array($kval, $sentMails)) {
                            if ($numberOfSentMails >= $this->sendPerCycle) {
                                $finished = false;
                                break;
                            }
                            $recipientData['uid'] = $kval;
                            $this->sendSingleMailAndAddLogEntry($mailUid, $recipientData, $recipientTable);
                            $numberOfSentMailsOfGroup++;
                            $numberOfSentMails++;
                        }
                    }
                } else {
                    $idList = implode(',', $listArr);
                    if ($idList) {
                        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                        $queryBuilder
                            ->select('*')
                            ->from($table)
                            ->where($queryBuilder->expr()->in('uid', $idList))
                            ->setMaxResults($this->sendPerCycle + 1);
                        if ($sentMails) {
                            $queryBuilder->andWhere($queryBuilder->expr()->notIn('uid', implode(',', $sentMails)));
                        }

                        $statement = $queryBuilder->execute();

                        while ($recipientData = $statement->fetchAssociative()) {
                            $recipientData['sys_dmail_categories_list'] = RecipientUtility::getListOfRecipientCategories($table, $recipientData['uid']);

                            if ($numberOfSentMails >= $this->sendPerCycle) {
                                $finished = false;
                                break;
                            }

                            // We are NOT finished!
                            $this->sendSingleMailAndAddLogEntry($mailUid, $recipientData, $recipientTable);
                            $numberOfSentMailsOfGroup++;
                            $numberOfSentMails++;
                        }
                    }
                }

                $this->logger->debug(LanguageUtility::getLL('dmailer_sending') . ' ' . $numberOfSentMailsOfGroup . ' ' . LanguageUtility::getLL('dmailer_sending_to_table') . ' ' . $table);
            }
        }
        return $finished;
    }

    /**
     * Sending the email and write to log.
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail table
     * @param array $recipientData Recipient's data array
     * @param string $recipientTable Table name
     *
     * @return void
     * @throws DBALException
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     * @throws \Exception
     */
    protected function sendSingleMailAndAddLogEntry(int $mailUid, array $recipientData, string $recipientTable): void
    {
        if (RecipientUtility::isMailSendToRecipient($mailUid, (int)$recipientData['uid'], $recipientTable) === false) {
            $pt = MailerUtility::getMilliseconds();
            $recipientData = RecipientUtility::normalizeAddress($recipientData);

            // write to dmail_maillog table. if it can be written, continue with sending.
            // if not, stop the script and report error
            $returnCode = 0;

            // try to insert the mail to the mail log repository
            try {
                $logUid = $this->sysDmailMaillogRepository->insertRecord($mailUid, $recipientTable . '_' . $recipientData['uid'], strlen($this->message),
                    MailerUtility::getMilliseconds() - $pt, $returnCode, $recipientData['email']);
            } catch (DBALException $exception) {
                $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mailUid . ')';
                $this->logger->critical($message);
                throw new \Exception($message, 1663340700, $exception);
            }

            // Send mail to recipient
            $returnCode = $this->sendPersonalizedMail($recipientData, $recipientTable);

            // try to store the sending return code
            try {
                $this->sysDmailMaillogRepository->updateRecord($logUid, strlen($this->message), MailerUtility::getMilliseconds() - $pt, $returnCode);
            } catch (DBALException $exception) {
                $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mailUid . ')';
                $this->logger->critical($message);
                throw new \Exception($message, 1663340700, $exception);
            }
        }
    }

    /**
     * Set job begin and end time. And send this to admin
     *
     * @param int $mailUid Sys_dmail UID
     * @param string $key Begin or end
     *
     * @return void
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setBeginEnd(int $mailUid, string $key): void
    {
        $subject = '';
        $message = '';

        $numberOfRecipients = MailerUtility::getNumberOfRecipients($mailUid);

        GeneralUtility::makeInstance(SysDmailRepository::class)->update($mailUid, ['scheduled_' . $key => time(), 'recipients' => $numberOfRecipients]);

        switch ($key) {
            case 'begin':
                $subject = LanguageUtility::getLL('dmailer_mid') . ' ' . $mailUid . ' ' . LanguageUtility::getLL('dmailer_job_begin');
                $message = LanguageUtility::getLL('dmailer_job_begin') . ': ' . date('d-m-y h:i:s');
                break;
            case 'end':
                $subject = LanguageUtility::getLL('dmailer_mid') . ' ' . $mailUid . ' ' . LanguageUtility::getLL('dmailer_job_end');
                $message = LanguageUtility::getLL('dmailer_job_end') . ': ' . date('d-m-y h:i:s');
                break;
            default:
                // do nothing
        }

        $this->logger->debug($subject . ': ' . $message);

        if ($this->notificationJob === true) {
            $fromName = $this->charsetConverter->conv($this->fromName, $this->charset, $this->backendCharset) ?? '';
            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail
                ->setSiteIdentifier($this->siteIdentifier)
                ->setTo($this->fromEmail, $fromName)
                ->setFrom($this->fromEmail, $fromName)
                ->setSubject($subject);

            if ($this->replyToEmail !== '') {
                $mail->setReplyTo($this->replyToEmail);
            }

            $mail->text($message);
            $mail->send();
        }
    }

    /**
     * Called from the dmailerd script.
     * Look if there is newsletter to be sent and do the sending process. Otherwise, quit runtime
     *
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function runcron(): void
    {
        $this->notificationJob = (bool)ConfigurationUtility::getExtensionConfiguration('notificationJob');

        if (!is_object(LanguageUtility::getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ?: $this->backendUserLanguage;
            LanguageUtility::getLanguageService()->init(trim($language));
        }

        // always include locallang file
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_mod2-6.xlf');

        $pt = MailerUtility::getMilliseconds();

        $this->logger->debug(LanguageUtility::getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));

        if ($row = GeneralUtility::makeInstance(SysDmailRepository::class)->findMailsToSend()) {
            $this->logger->debug(LanguageUtility::getLL('dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . LanguageUtility::getLL('dmailer_processed'));
            $this->prepare($row);
            $query_info = unserialize($row['query_info']);

            if (!$row['scheduled_begin']) {
                // Hook to alter the list of recipients
                if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'])) {
                    $queryInfoHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'];
                    if (is_array($queryInfoHook)) {
                        $hookParameters = [
                            'row' => $row,
                            'query_info' => &$query_info,
                        ];
                        $hookReference = &$this;
                        foreach ($queryInfoHook as $hookFunction) {
                            GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                        }
                    }
                }
                $this->setBeginEnd((int)$row['uid'], 'begin');
            }

            $finished = !is_array($query_info['id_lists']) || $this->massSend($query_info['id_lists'], $row['uid']);

            if ($finished) {
                $this->setBeginEnd((int)$row['uid'], 'end');
            }
        } else {
            $this->logger->debug(LanguageUtility::getLL('dmailer_nothing_to_do'));
        }

        $parsetime = MailerUtility::getMilliseconds() - $pt;
        $this->logger->debug(LanguageUtility::getLL('dmailer_ending') . ' ' . $parsetime . ' ms');
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the mailbody
     *
     * @return void
     * @var MailMessage $mailMessage Mailer Message Object
     */
    protected function setContent(MailMessage $mailMessage): void
    {
        // todo: css??
        // iterate through the media array and embed them
        if ($this->includeMedia && !empty($this->getHtmlContent())) {
            // extract all media path from the mail message
            $this->mailParts['html']['media'] = MailerUtility::extractMediaLinks($this->getHtmlContent(), $this->getHtmlPath());
            foreach ($this->mailParts['html']['media'] as $media) {
                // TODO: why are there table related tags here?
                if (!($media['do_not_embed'] ?? false) && !($media['use_jumpurl'] ?? false) && $media['tag'] === 'img') {
                    if (ini_get('allow_url_fopen')) {
                        $mailMessage->embed(fopen($media['absRef'], 'r'), basename($media['absRef']));
                    } else {
                        $mailMessage->embed(GeneralUtility::getUrl($media['absRef']), basename($media['absRef']));
                    }
                    $this->setHtmlContent(str_replace($media['subst_str'], 'cid:' . basename($media['absRef']), $this->getHtmlContent()));
                }
            }
            // remove ` do_not_embed="1"` attributes
            $this->setHtmlContent(str_replace(' do_not_embed="1"', '', $this->getHtmlContent()));
        }

        // set the html content
        if ($this->getHtmlContent()) {
            $mailMessage->html($this->getHtmlContent());
        }
        // set the plain content as alt part
        if ($this->getPlainContent()) {
            $mailMessage->text($this->getPlainContent());
        }

        // handle FAL attachments
        if ((int)$this->dmailer['sys_dmail_rec']['attachment'] > 0) {
            $files = MailerUtility::getAttachments($this->dmailer['sys_dmail_rec']['uid']);
            /** @var FileReference $file */
            foreach ($files as $file) {
                $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();
                $mailMessage->attachFromPath($filePath);
            }
        }
    }

    /**
     * Send of the email using php mail function.
     *
     * @param string|Address $recipient The recipient to send the mail to
     * @param array|null $recipientData Recipient's data array
     * @return void
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function sendMailToRecipient(Address|string $recipient, array $recipientData = null): void
    {
        /** @var MailMessage $mailer */
        $mailer = GeneralUtility::makeInstance(MailMessage::class);
        $mailer
            ->setSiteIdentifier($this->siteIdentifier)
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($recipient)
            ->subject($this->subject)
            ->priority($this->priority);

        if ($this->replyToEmail) {
            $mailer->replyTo(new Address($this->replyToEmail, $this->replyToName));
        } else {
            $mailer->replyTo(new Address($this->fromEmail, $this->fromName));
        }

        if (GeneralUtility::validEmail($this->dmailer['sys_dmail_rec']['return_path'])) {
            $mailer->sender($this->dmailer['sys_dmail_rec']['return_path']);
        }

        $this->setContent($mailer);

        // setting additional header
        // organization and TYPO3MID
        $header = $mailer->getHeaders();
        if ($this->TYPO3MID) {
            $header->addTextHeader('X-TYPO3MID', $this->TYPO3MID);
        }

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // Hook to edit or add the mail headers
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'])) {
            $mailHeadersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'];
            if (is_array($mailHeadersHook)) {
                $hookParameters = [
                    'row' => &$recipientData,
                    'header' => &$header,
                ];
                $hookReference = &$this;
                foreach ($mailHeadersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        $mailer->send();
        unset($mailer);
    }

}
