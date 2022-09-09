<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Utility\MailUtility;
use PDO;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;

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
    protected bool $mailHasContent = false;
    protected bool $isHtml = false;
    protected bool $isPlain = false;
    protected bool $includeMedia = false;
    protected bool $flowedFormat = false;
    protected string $user_dmailerLang = 'en';
    protected bool $isTestMail = false;
    protected string $charset = 'utf-8';
    protected string $messageId = '';
    protected string $subject = '';
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $organisation = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected int $priority = 0;
    protected string $authCodeFieldList = '';
    protected string $mediaList = '';
    protected string $backendCharset = 'utf-8';
    protected string $message = '';
    protected bool $notificationJob = false;
    protected string $jumpUrlPrefix = '';
    protected bool $jumpUrlUseMailto = false;
    protected bool $jumpUrlUseId = false;

    public function __construct(protected CharsetConverter $charsetConverter)
    {
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
        return $this->mailParts[$part];
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

    public function getHtmlHrefs(): array
    {
        return $this->mailParts['html']['hrefs'];
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

    /**
     * Adds plain-text, replaces the HTTP urls in the plain text and then encodes it
     *
     * @param string $content The plain text content
     *
     * @return void
     */
    public function addPlainContent(string $content): void
    {
        $this->setPlainContent($content);
        MailUtility::substHTTPurlsInPlainText($this);
    }

    public function setPlainLinkIds($array): void
    {
        $this->mailParts['plain']['link_ids'] = $array;
    }

    /**
     * Sets the plain-text part. No processing done.
     *
     * @param string $content The plain content
     *
     * @return    void
     */
    public function setPlainContent(string $content): void
    {
        $this->mailParts['plain']['content'] = $content;
    }

    public function getPlainContent(): string
    {
        return $this->mailParts['plain']['content'];
    }

    /**
     * Sets the HTML-part. No processing done.
     *
     * @param string $content The HTML content
     *
     * @return void
     */
    public function setHtmlContent(string $content): void
    {
        $this->mailParts['html']['content'] = $content;
    }

    public function getHtmlContent(): string
    {
        return $this->mailParts['html']['content'];
    }

    public function getHtmlPath(): string
    {
        return $this->mailParts['html']['path'];
    }

    /**
     * Preparing the Email. Headers are set in global variables
     *
     * @param array $row Record from the sys_dmail table
     *
     * @return void
     */
    public function dmailer_prepare(array $row): void
    {
        $sys_dmail_uid = $row['uid'];
        if ($row['flowedFormat']) {
            $this->flowedFormat = true;
        }
        if ($row['charset']) {
            if ($row['type'] == 0) {
                $this->charset = 'utf-8';
            } else {
                $this->charset = $row['charset'];
            }
        }

        $this->mailParts = unserialize(base64_decode($row['mailContent']));
        $this->messageId = $this->mailParts['messageid'];

        $this->subject = $this->charsetConverter->conv($row['subject'], $this->backendCharset, $this->charset);

        $this->fromEmail = $row['from_email'];
        $this->fromName = ($row['from_name'] ? $this->charsetConverter->conv($row['from_name'], $this->backendCharset, $this->charset) : '');

        $this->replyToEmail = ($row['replyto_email'] ?: '');
        $this->replyToName = ($row['replyto_name'] ? $this->charsetConverter->conv($row['replyto_name'], $this->backendCharset, $this->charset) : '');

        $this->organisation = ($row['organisation'] ? $this->charsetConverter->conv($row['organisation'], $this->backendCharset, $this->charset) : '');

        $this->priority = MailUtility::intInRangeWrapper((int)$row['priority'], 1, 5);
        $this->authCodeFieldList = ($row['authcode_fieldList'] ?: 'uid');

        $this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
        $this->dmailer['html_content'] = $this->mailParts['html']['content'] ?? '';
        $this->dmailer['plain_content'] = $this->mailParts['plain']['content'] ?? '';
        $this->dmailer['messageID'] = $this->messageId;
        $this->dmailer['sys_dmail_uid'] = $sys_dmail_uid;
        $this->dmailer['sys_dmail_rec'] = $row;

        $this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['html_content']);
        foreach ($this->dmailer['boundaryParts_html'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_html'][$bKey] = explode('-->', $bContent, 2);

            // Remove useless HTML comments
            if (substr($this->dmailer['boundaryParts_html'][$bKey][0], 1) == 'END') {
                $this->dmailer['boundaryParts_html'][$bKey][1] = $this->removeHTMLComments($this->dmailer['boundaryParts_html'][$bKey][1]);
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

        $this->isHtml = (bool)($this->mailParts['html']['content'] ?? false);
        $this->isPlain = (bool)($this->mailParts['plain']['content'] ?? false);
        $this->includeMedia = (bool)$row['includeMedia'];
    }

    /**
     * Removes html comments when outside script and style pairs
     *
     * @param string $content The email content
     *
     * @return string HTML content without comments
     */
    public function removeHTMLComments(string $content): string
    {
        $content = preg_replace('/\/\*<!\[CDATA\[\*\/[\t\v\n\r\f]*<!--/', '/*<![CDATA[*/', $content);
        $content = preg_replace('/[\t\v\n\r\f]*<!(?:--[^\[<>][\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
        return preg_replace('/\/\*<!\[CDATA\[\*\//', '/*<![CDATA[*/<!--', $content);
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param string $content The HTML or plaintext part
     * @param array $recipRow Recipient's data array
     * @param array $markers Existing markers that are mail-specific, not user-specific
     *
     * @return string Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function replaceMailMarkers(string $content, array $recipRow, array $markers): string
    {
        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['defaultRecipFields']);
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']));
        }

        foreach ($rowFieldsArray as $substField) {
            if (isset($recipRow[$substField])) {
                $markers['###USER_' . $substField . '###'] = $this->charsetConverter->conv($recipRow[$substField], $this->backendCharset, $this->charset);
            }
        }

        // uppercase fields with uppercased values
        $uppercaseFieldsArray = ['name', 'firstname'];
        foreach ($uppercaseFieldsArray as $substField) {
            if (isset($recipRow[$substField])) {
                $markers['###USER_' . strtoupper($substField) . '###'] = strtoupper($this->charsetConverter->conv($recipRow[$substField], $this->backendCharset, $this->charset));
            }
        }

        // Hook allows to manipulate the markers to add salutation etc.
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'])) {
            $mailMarkersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'];
            if (is_array($mailMarkersHook)) {
                $hookParameters = [
                    'row' => &$recipRow,
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
     * Replace the marker with recipient data and then send it
     *
     * @param array $recipRow Recipient's data array
     * @param string $tableNameChar Tablename, from which the recipient come from
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function dmailer_sendAdvanced(array $recipRow, string $tableNameChar): int
    {
        $returnCode = 0;
        $tempRow = [];

        // check recipRow for HTML
        foreach ($recipRow as $k => $v) {
            $tempRow[$k] = is_string($v) ? htmlspecialchars($v) : $v;
        }
        unset($recipRow);
        $recipRow = $tempRow;

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipRow['email'] = trim($recipRow['email']);

        if ($recipRow['email']) {
            $midRidId = 'MID' . $this->dmailer['sys_dmail_uid'] . '_' . $tableNameChar . $recipRow['uid'];
            $uniqMsgId = md5(microtime()) . '_' . $midRidId;
            // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.3/Deprecation-94309-DeprecatedGeneralUtilitystdAuthCode.html
            $authCode = GeneralUtility::stdAuthCode($recipRow, $this->authCodeFieldList); //@TODO

            $additionalMarkers = [
                // Put in the tablename of the userinformation
                '###SYS_TABLE_NAME###' => $tableNameChar,
                // Put in the uid of the mail-record
                '###SYS_MAIL_ID###' => $this->dmailer['sys_dmail_uid'],
                '###SYS_AUTHCODE###' => $authCode,
                // Put in the unique message id in HTML-code
                $this->dmailer['messageID'] => $uniqMsgId,
            ];

            $this->mediaList = '';
            $this->mailParts['html']['content'] = '';
            if ($this->isHtml && ($recipRow['module_sys_dmail_html'] || $tableNameChar == 'P')) {
                $tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], $recipRow['sys_dmail_categories_list']);
                if ($this->mailHasContent) {
                    $tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipRow, $additionalMarkers);
                    $this->mailParts['html']['content'] = $tempContent_HTML;
                    $returnCode |= 1;
                }
            }

            // Plain
            $this->mailParts['plain']['content'] = '';
            if ($this->isPlain) {
                $tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'], $recipRow['sys_dmail_categories_list']);
                if ($this->mailHasContent) {
                    $tempContent_Plain = $this->replaceMailMarkers($tempContent_Plain, $recipRow, $additionalMarkers);
                    if (trim($this->dmailer['sys_dmail_rec']['use_rdct']) || trim($this->dmailer['sys_dmail_rec']['long_link_mode'])) {
                        $tempContent_Plain = MailUtility::substUrlsInPlainText($tempContent_Plain, $this->dmailer['sys_dmail_rec']['long_link_mode'] ? 'all' : '76', $this->dmailer['sys_dmail_rec']['long_link_rdct_url']);
                    }
                    $this->mailParts['plain']['content'] = $tempContent_Plain;
                    $returnCode |= 2;
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->dmailer['sys_dmail_rec']['return_path'] = str_replace('###XID###', $midRidId, $this->dmailer['sys_dmail_rec']['return_path']);

            // check if the email valids
            $recipient = [];
            if (GeneralUtility::validEmail($recipRow['email'])) {
                $email = $recipRow['email'];
                $name = $this->ensureCorrectEncoding($recipRow['name']);

                $recipient = MailUtility::createRecipient($email, $name);
            }

            if ($returnCode && !empty($recipient)) {
                $this->sendTheMail($recipient, $recipRow);
            }
        }
        return $returnCode;
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param string $addressList list of recipient address, comma list of emails
     *
     * @return    bool
     */
    public function dmailer_sendSimple(string $addressList): bool
    {
        if ($this->mailParts['html']['content'] ?? false) {
            $this->mailParts['html']['content'] = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], '-1');
        } else {
            $this->mailParts['html']['content'] = '';
        }
        if ($this->mailParts['plain']['content'] ?? false) {
            $this->mailParts['plain']['content'] = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'], '-1');
        } else {
            $this->mailParts['plain']['content'] = '';
        }

        $recipients = explode(',', $addressList);
        foreach ($recipients as $recipient) {
            $this->sendTheMail($recipient);
        }

        return true;
    }

    /**
     * This function checks which content elements are suppsed to be sent to the recipient.
     * tslib_content inserts dmail boudary markers in the content specifying which elements are intended for which categories,
     * this functions check if the recipeient is subscribing to any of these categories and
     * filters out the elements that are inteded for categories not subscribed to.
     *
     * @param array $cArray Array of content split by dmail boundary
     * @param string $userCategories The list of categories the user is subscribing to.
     *
     * @return    string        Content of the email, which the recipient subscribed
     */
    public function dmailer_getBoundaryParts(array $cArray, string $userCategories): string
    {
        $returnVal = '';
        $this->mailHasContent = false;
        $boundaryMax = count($cArray) - 1;
        foreach ($cArray as $bKey => $cP) {
            $key = substr($cP[0], 1);
            $isSubscribed = false;
            $cP['mediaList'] = $cP['mediaList'] ?? '';
            if (!$key || (intval($userCategories) == -1)) {
                $returnVal .= $cP[1];
                $this->mediaList .= $cP['mediaList'];
                if ($cP[1]) {
                    $this->mailHasContent = true;
                }
            } else if ($key == 'END') {
                $returnVal .= $cP[1];
                $this->mediaList .= $cP['mediaList'];
                // There is content, and it is not just the header and footer content, or it is the only content because we have no direct mail boundaries.
                if (($cP[1] && !($bKey == 0 || $bKey == $boundaryMax)) || count($cArray) == 1) {
                    $this->mailHasContent = true;
                }
            } else {
                foreach (explode(',', $key) as $group) {
                    if (GeneralUtility::inList($userCategories, $group)) {
                        $isSubscribed = true;
                    }
                }
                if ($isSubscribed) {
                    $returnVal .= $cP[1];
                    $this->mediaList .= $cP['mediaList'];
                    $this->mailHasContent = true;
                }
            }
        }
        return $returnVal;
    }

    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table Tablename of the recipient
     * @param int $uid Uid of the recipient
     *
     * @return    string        list of categories
     * @throws DBALException
     * @throws Exception
     */
    public function getListOfRecipentCategories(string $table, int $uid): string
    {
        if ($table === 'PLAINLIST') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select($relationTable . '.uid_foreign')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_local = ' . $table . '.uid')
            ->where($queryBuilder->expr()->eq($relationTable . '.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->execute();

        $list = '';
        while ($row = $statement->fetchAssociative()) {
            $list .= $row['uid_foreign'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * Mass send to recipient in the list
     *
     * @param array $query_info List of recipients' ID in the sys_dmail table
     * @param int $mid Directmail ID. UID of the sys_dmail table
     * @return boolean
     * @throws DBALException
     * @throws Exception
     */
    public function dmailer_masssend_list(array $query_info, int $mid): bool
    {
        $enableFields['tt_address'] = 'tt_address.deleted=0 AND tt_address.hidden=0';

        $c = 0;
        $returnVal = true;
        if (is_array($query_info['id_lists'])) {
            foreach ($query_info['id_lists'] as $table => $listArr) {
                if (is_array($listArr)) {
                    $ct = 0;
                    // Find tKey
                    $tKey = match ($table) {
                        'tt_address', 'fe_users' => substr($table, 0, 1),
                        'PLAINLIST' => 'P',
                        default => 'u',
                    };

                    // Send mails
                    $sendIds = $this->dmailer_getSentMails($mid, $tKey);
                    if ($table == 'PLAINLIST') {
                        $sendIdsArr = explode(',', $sendIds);
                        foreach ($listArr as $kval => $recipRow) {
                            $kval++;
                            if (!in_array($kval, $sendIdsArr)) {
                                if ($c >= $this->sendPerCycle) {
                                    $returnVal = false;
                                    break;
                                }
                                $recipRow['uid'] = $kval;
                                $this->shipOfMail($mid, $recipRow, $tKey);
                                $ct++;
                                $c++;
                            }
                        }
                    } else {
                        $idList = implode(',', $listArr);
                        if ($idList) {
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $statement = $queryBuilder
                                ->select('*')
                                ->from($table)
                                ->where($queryBuilder->expr()->in('uid', $idList))
                                ->andWhere($queryBuilder->expr()->notIn('uid', ($sendIds ?: 0)))
                                ->setMaxResults($this->sendPerCycle + 1)
                                ->execute();

                            while ($recipRow = $statement->fetch()) {
                                $recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table, $recipRow['uid']);

                                if ($c >= $this->sendPerCycle) {
                                    $returnVal = false;
                                    break;
                                }

                                // We are NOT finished!
                                $this->shipOfMail($mid, $recipRow, $tKey);
                                $ct++;
                                $c++;
                            }
                        }
                    }

                    $this->logger->debug(MailUtility::getLanguageService()->getLL('dmailer_sending') . ' ' . $ct . ' ' . MailUtility::getLanguageService()->getLL('dmailer_sending_to_table') . ' ' . $table);
                }
            }
        }
        return $returnVal;
    }

    /**
     * Sending the email and write to log.
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail table
     * @param array $recipRow Recipient's data array
     * @param string $tableKey Table name
     *
     * @return    void
     * @throws DBALException
     * @internal param string $tKey : table of the recipient
     *
     */
    public function shipOfMail(int $mid, array $recipRow, string $tableKey): void
    {
        if ($this->dmailer_isSend($mid, (int)$recipRow['uid'], $tableKey) === false) {
            $pt = MailUtility::getMilliseconds();
            $recipRow = MailUtility::convertFields($recipRow);

            // write to dmail_maillog table. if it can be written, continue with sending.
            // if not, stop the script and report error
            $rC = 0;
            $logUid = $this->dmailer_addToMailLog($mid, $tableKey . '_' . $recipRow['uid'], strlen($this->message), MailUtility::getMilliseconds() - $pt, $rC, $recipRow['email']);

            if ($logUid) {
                $rC = $this->dmailer_sendAdvanced($recipRow, $tableKey);
                $parsetime = MailUtility::getMilliseconds() - $pt;

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
                $ok = $queryBuilder
                    ->update('sys_dmail_maillog')
                    ->set('tstamp', time())
                    ->set('size', strlen($this->message))
                    ->set('parsetime', $parsetime)
                    ->set('html_sent', $rC)
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($logUid, PDO::PARAM_INT)))
                    ->execute();

                if ($ok === false) {
                    $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')';
                    $this->logger->critical($message);
                    die($message);
                }
            } else {
                // stop the script if dummy log can't be made
                $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')';
                $this->logger->critical($message);
                die($message);
            }
        }
    }

    /**
     * Set job begin and end time. And send this to admin
     *
     * @param int $mid Sys_dmail UID
     * @param string $key Begin or end
     *
     * @return void
     * @throws DBALException
     */
    public function dmailer_setBeginEnd(int $mid, string $key): void
    {
        $subject = '';
        $message = '';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
            ->update('sys_dmail')
            ->set('scheduled_' . $key, time())
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)))
            ->execute();

        switch ($key) {
            case 'begin':
                $subject = MailUtility::getLanguageService()->getLL('dmailer_mid') . ' ' . $mid . ' ' . MailUtility::getLanguageService()->getLL('dmailer_job_begin');
                $message = MailUtility::getLanguageService()->getLL('dmailer_job_begin') . ': ' . date('d-m-y h:i:s');
                break;
            case 'end':
                $subject = MailUtility::getLanguageService()->getLL('dmailer_mid') . ' ' . $mid . ' ' . MailUtility::getLanguageService()->getLL('dmailer_job_end');
                $message = MailUtility::getLanguageService()->getLL('dmailer_job_end') . ': ' . date('d-m-y h:i:s');
                break;
            default:
                // do nothing
        }

        $this->logger->debug($subject . ': ' . $message);

        if ($this->notificationJob === true) {
            $from_name = $this->charsetConverter->conv($this->fromName, $this->charset, $this->backendCharset) ?? '';

            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail->setTo($this->fromEmail, $from_name);
            $mail->setFrom($this->fromEmail, $from_name);
            $mail->setSubject($subject);

            if ($this->replyToEmail !== '') {
                $mail->setReplyTo($this->replyToEmail);
            }

            $mail->text($message);
            $mail->send();
        }
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail record
     * @param int $rid Recipient UID
     * @param string $rtbl Recipient table
     *
     * @return    bool Number of found records
     * @throws DBALException
     */
    public function dmailer_isSend(int $mid, int $rid, string $rtbl): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');

        $statement = $queryBuilder
            ->select('uid')
            ->from('sys_dmail_maillog')
            ->where($queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($rid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($rtbl)))
            ->andWhere($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        return (bool)$statement->rowCount();
    }

    /**
     * Get IDs of recipient, which has been sent
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail record
     * @param string $rtbl Recipient table
     *
     * @return    string        list of sent recipients
     * @throws DBALException
     */
    public function dmailer_getSentMails(int $mid, string $rtbl): string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $statement = $queryBuilder
            ->select('rid')
            ->from('sys_dmail_maillog')
            ->where($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($rtbl)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        $list = '';

        while (($row = $statement->fetch())) {
            $list .= $row['rid'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param string $rid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parsetime Parse time of the email
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return int
     * @throws DBALException
     */
    public function dmailer_addToMailLog(int $mid, string $rid, int $size, int $parsetime, int $html, string $email): int
    {
        [$rtbl, $rid] = explode('_', $rid);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $queryBuilder
            ->insert('sys_dmail_maillog')
            ->values([
                'mid' => $mid,
                'rtbl' => $rtbl,
                'rid' => $rid,
                'email' => $email,
                'tstamp' => time(),
                'url' => '',
                'size' => $size,
                'parsetime' => $parsetime,
                'html_sent' => $html,
            ])
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId('sys_dmail_maillog');
    }

    /**
     * Called from the dmailerd script.
     * Look if there is newsletter to be sent and do the sending process. Otherwise, quit runtime
     *
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    public function runcron(): void
    {
        $this->sendPerCycle = trim($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) ? intval($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) : 50;
        $this->notificationJob = (bool)($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['notificationJob']);

        if (!is_object(MailUtility::getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ?: $this->user_dmailerLang;
            MailUtility::getLanguageService()->init(trim($language));
        }

        // always include locallang file
        MailUtility::getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');

        $pt = MailUtility::getMilliseconds();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select('*')
            ->from('sys_dmail')
            ->where($queryBuilder->expr()->neq('scheduled', '0'))
            ->andWhere($queryBuilder->expr()->lt('scheduled', time()))
            ->andWhere($queryBuilder->expr()->eq('scheduled_end', '0'))
            ->andWhere($queryBuilder->expr()->notIn('type', ['2', '3']))
            ->orderBy('scheduled')
            ->execute();

        $this->logger->debug(MailUtility::getLanguageService()->getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));

        if (($row = $statement->fetch())) {
            $this->logger->debug(MailUtility::getLanguageService()->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . MailUtility::getLanguageService()->getLL('dmailer_processed'));
            $this->dmailer_prepare($row);
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
                $this->dmailer_setBeginEnd((int)$row['uid'], 'begin');
            }

            $finished = $this->dmailer_masssend_list($query_info, $row['uid']);

            if ($finished) {
                $this->dmailer_setBeginEnd((int)$row['uid'], 'end');
            }
        } else {
            $this->logger->debug(MailUtility::getLanguageService()->getLL('dmailer_nothing_to_do'));
        }

        $parsetime = MailUtility::getMilliseconds() - $pt;
        $this->logger->debug(MailUtility::getLanguageService()->getLL('dmailer_ending') . ' ' . $parsetime . ' ms');
    }

    /**
     * Initializing the MailMessage class and setting the first global variables. Write to log file if it's a cronjob
     *
     * @param int $user_dmailer_sendPerCycle Total of recipient in a cycle
     * @param string $user_dmailer_lang Language of the user
     *
     * @return    void
     */
    public function start(int $user_dmailer_sendPerCycle = 50, string $user_dmailer_lang = 'en'): void
    {

        // Sets the message id
        $host = MailUtility::getHostname();
        if (!$host || $host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
            $host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
        }

        $idLeft = time() . '.' . uniqid();
        $idRight = !empty($host) ? $host : 'symfony.generated';
        $this->messageId = $idLeft . '@' . $idRight;

        // Default line break for Unix systems.
        $this->linebreak = LF;
        // Line break for Windows. This is needed because PHP on Windows systems
        // send mails via SMTP instead of using sendmail, and thus the linebreak needs to be \r\n.
        if (Environment::isWindows()) {
            $this->linebreak = CRLF;
        }

        // Mailer engine parameters
        $this->sendPerCycle = $user_dmailer_sendPerCycle;
        $this->user_dmailerLang = $user_dmailer_lang;
        if (isset($this->nonCron) && !$this->nonCron) {
            $this->logger->debug('Starting directmail cronjob');
        }
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the mailbody
     *
     * @return void
     * @var MailMessage $mailer Mailer Object
     */
    public function setContent(MailMessage &$mailer): void
    {
        // todo: css??
        // iterate through the media array and embed them
        if ($this->includeMedia && !empty($this->mailParts['html']['content'])) {
            // extract all media path from the mail message
            $this->extractMediaLinks();
            foreach ($this->mailParts['html']['media'] as $media) {
                // TODO: why are there table related tags here?
                if (($media['tag'] === 'img' || $media['tag'] === 'table' || $media['tag'] === 'tr' || $media['tag'] === 'td') && !$media['use_jumpurl'] && !$media['do_not_embed']) {
                    if (ini_get('allow_url_fopen')) {
                        $mailer->embed(fopen($media['absRef'], 'r'), basename($media['absRef']));
                    } else {
                        $mailer->embed(GeneralUtility::getUrl($media['absRef']), basename($media['absRef']));
                    }
                    $this->mailParts['html']['content'] = str_replace($media['subst_str'], 'cid:' . basename($media['absRef']), $this->mailParts['html']['content']);
                }
            }
            // remove ` do_not_embed="1"` attributes
            $this->mailParts['html']['content'] = str_replace(' do_not_embed="1"', '', $this->mailParts['html']['content']);
        }

        // set the html content
        if ($this->mailParts['html']['content']) {
            $mailer->html($this->mailParts['html']['content']);
        }
        // set the plain content as alt part
        if ($this->mailParts['plain']['content']) {
            $mailer->text($this->mailParts['plain']['content']);
        }

        // handle FAL attachments
        if ((int)$this->dmailer['sys_dmail_rec']['attachment'] > 0) {
            $files = MailUtility::getAttachments($this->dmailer['sys_dmail_rec']['uid']);
            /** @var FileReference $file */
            foreach ($files as $file) {
                $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();
                $mailer->attachFromPath($filePath);
            }
        }
    }

    /**
     * Send of the email using php mail function.
     *
     * @param string|Address $recipient The recipient to send the mail to
     * @param array|null $recipRow Recipient's data array
     *
     * @return    void
     */
    public function sendTheMail(Address|string $recipient, array $recipRow = null): void
    {
        /** @var MailMessage $mailer */
        $mailer = GeneralUtility::makeInstance(MailMessage::class);
        $mailer
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

        // TODO: setContent should set the images (includeMedia) or add attachment
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
                    'row' => &$recipRow,
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


    /**
     * Add HTML to an email
     *
     * @param string $file String location of the HTML
     *
     * @return bool|string bool: HTML fetch status. string: if HTML is a frameset.
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function addHTML(string $file): bool|string
    {
        // Adds HTML and media, encodes it from a URL or file
        $status = $this->fetchHTML($file);
        if (!$status) {
            return false;
        }
        if (MailUtility::extractFramesInfo($this->mailParts['html']['content'], $this->mailParts['html']['path'])) {
            return 'Document was a frameset. Stopped';
        }
        $this->extractHyperLinks();
        MailUtility::substHREFsInHTML($this);
        $this->setHtmlContent($this->mailParts['html']['content']);
        return true;
    }

    /**
     * Fetches the HTML-content from either url or local server file
     *
     * @param string $url Url of the html to fetch
     *
     * @return bool Whether the data was fetched or not
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function fetchHTML(string $url): bool
    {
        // Fetches the content of the page
        $this->mailParts['html']['content'] = GeneralUtility::getURL($url);
        if ($this->mailParts['html']['content']) {
            $urlPart = parse_url($url);
            if (MailUtility::getExtensionConfiguration('UseHttpToFetch')) {
                $urlPart['scheme'] = 'http';
            }

            $user = '';
            if (!empty($urlPart['user'])) {
                $user = $urlPart['user'];
                if (!empty($urlPart['pass'])) {
                    $user .= ':' . $urlPart['pass'];
                }
                $user .= '@';
            }

            $this->mailParts['html']['path'] = $urlPart['scheme'] . '://' . $user . $urlPart['host'] . GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');

            return true;
        }
        return false;
    }

    /**
     * Extracts all media-links from $this->theParts['html']['content']
     *
     * @return    void
     */
    public function extractMediaLinks(): void
    {
        $this->mailParts['html']['media'] = [];

        $htmlContent = $this->mailParts['html']['content'];
        $attribRegex = MailUtility::tag_regex(['img', 'table', 'td', 'tr', 'body', 'iframe', 'script', 'input', 'embed']);
        $imageList = '';

        // split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $len = strlen($codepieces[0]);
        $pieces = count($codepieces);
        $reg = [];
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codepieces[$i]) + 2;
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = MailUtility::get_tag_attributes($reg[0]);
            $imageData = [];

            // Finds the src or background attribute
            $imageData['ref'] = ($attributes['src'] ?? $attributes['background'] ?? '');
            if ($imageData['ref']) {
                // find out if the value had quotes around it
                $imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = MailUtility::absRef($imageData['ref'], $this->mailParts['html']['path']);
                    $imageData['tag'] = $tag;
                    $imageData['use_jumpurl'] = (isset($attributes['dmailerping']) && $attributes['dmailerping']) ? 1 : 0;
                    $imageData['do_not_embed'] = !empty($attributes['do_not_embed']);
                    $this->mailParts['html']['media'][] = $imageData;
                }
            }
        }

        // Extracting stylesheets
        $attribRegex = MailUtility::tag_regex(['link']);
        // Split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
            // fetches the attributes for the tag
            $attributes = MailUtility::get_tag_attributes($reg[0]);
            $imageData = [];
            if (strtolower($attributes['rel']) == 'stylesheet' && $attributes['href']) {
                // Finds the src or background attribute
                $imageData['ref'] = $attributes['href'];
                // Finds out if the value had quotes around it
                $imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = MailUtility::absRef($imageData['ref'], $this->mailParts['html']['path']);
                    $this->mailParts['html']['media'][] = $imageData;
                }
            }
        }

        // fixes javascript rollovers
        $codepieces = explode('.src', $htmlContent);
        $pieces = count($codepieces);
        $expr = '/^[^' . quotemeta('"') . quotemeta("'") . ']*/';
        for ($i = 1; $i < $pieces; $i++) {
            $temp = $codepieces[$i];
            $temp = trim(str_replace('=', '', trim($temp)));
            preg_match($expr, substr($temp, 1, strlen($temp)), $reg);
            $imageData['ref'] = $reg[0];
            $imageData['quotes'] = substr($temp, 0, 1);
            // subst_str is the string to look for, when substituting lateron
            $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
            $theInfo = GeneralUtility::split_fileref($imageData['ref']);

            switch ($theInfo['fileext']) {
                case 'gif':
                    // do like jpg
                case 'jpeg':
                    // do like jpg
                case 'jpg':
                    if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                        $imageList .= '|' . $imageData['subst_str'] . '|';
                        $imageData['absRef'] = MailUtility::absRef($imageData['ref'], $this->mailParts['html']['path']);
                        $this->mailParts['html']['media'][] = $imageData;
                    }
                    break;
                default:
                    // do nothing
            }
        }
    }

    /**
     * Extracts all hyper-links from $this->theParts["html"]["content"]
     *
     * @return    void
     */
    public function extractHyperLinks(): void
    {
        $linkList = '';

        $htmlContent = $this->mailParts['html']['content'];
        $attribRegex = MailUtility::tag_regex(['a', 'form', 'area']);

        // Splits the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $len = strlen($codepieces[0]);
        $pieces = count($codepieces);
        $reg = [];
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codepieces[$i]) + 2;
            preg_match('/[^>]*/', $codepieces[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = MailUtility::get_tag_attributes($reg[0], false);
            $hrefData = [];
            $hrefData['ref'] = ($attributes['href'] ?? '') ?: ($attributes['action'] ?? '');
            $quotes = (str_starts_with($hrefData['ref'], '"')) ? '"' : '';
            $hrefData['ref'] = trim($hrefData['ref'], '"');
            if ($hrefData['ref']) {
                // Finds out if the value had quotes around it
                $hrefData['quotes'] = $quotes;
                // subst_str is the string to look for when substituting later on
                $hrefData['subst_str'] = $quotes . $hrefData['ref'] . $quotes;
                if ($hrefData['ref'] && !str_starts_with(trim($hrefData['ref']), '#') && !str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = MailUtility::absRef($hrefData['ref'], $this->mailParts['html']['path']);
                    $hrefData['tag'] = $tag;
                    $hrefData['no_jumpurl'] = intval(trim(($attributes['no_jumpurl'] ?? ''), '"')) ? 1 : 0;
                    $this->mailParts['html']['hrefs'][] = $hrefData;
                }
            }
        }
        // Extracts TYPO3 specific links made by the openPic() JS function
        $codepieces = explode("onClick=\"openPic('", $htmlContent);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $showpicArray = explode("'", $codepieces[$i]);
            $hrefData['ref'] = $showpicArray[0];
            if ($hrefData['ref']) {
                $hrefData['quotes'] = "'";
                // subst_str is the string to look for, when substituting lateron
                $hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
                if (!str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = MailUtility::absRef($hrefData['ref'], $this->mailParts['html']['path']);
                    $this->mailParts['html']['hrefs'][] = $hrefData;
                }
            }
        }

        // substitute dmailerping URL
        // get all media and search for use_jumpurl then add it to the hrefs array
        $this->extractMediaLinks();
        foreach ($this->mailParts['html']['media'] as $mediaData) {
            if ($mediaData['use_jumpurl'] === 1) {
                $this->mailParts['html']['hrefs'][$mediaData['ref']] = $mediaData;
            }
        }
    }

    /**
     * @param string $payload
     * @return string
     */
    protected function ensureCorrectEncoding(string $payload): string
    {
        return $this
            ->charsetConverter
            ->conv(
                $payload,
                $this->backendCharset,
                $this->charset
            );
    }
}
