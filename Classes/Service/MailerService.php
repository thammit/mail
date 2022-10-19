<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Enumeration\SendFormat;
use MEDIAESSENZ\Mail\Mail\MailMessage;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
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
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class MailerService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * special header to identify returned mail
     *
     * @var string
     */
    protected string $TYPO3MID = '';

    /*
     * @var array the mail parts (HTML and Plain, incl. href and link to media)
     */
    protected int $mailUid = 0;
    protected array $mailParts = [];
    protected int $sendPerCycle = 50;
    protected bool $isHtml = false;
    protected bool $isPlain = false;
    protected bool $includeMedia = false;
    protected bool $flowedFormat = false;
    protected string $backendUserLanguage = 'default';
    protected bool $isTestMail = false;
    protected string $charset = 'utf-8';
    protected string $subject = '';
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $organisation = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected string $returnPath = '';
    protected int $priority = 3;
    protected string $authCodeFieldList = '';
    protected string $backendCharset = 'utf-8';
    protected string $message = '';
    protected bool $notificationJob = false;
    protected string $jumpUrlPrefix = '';
    protected bool $jumpUrlUseMailto = false;
    protected bool $jumpUrlUseId = false;
    protected bool $redirect = false;
    protected bool $redirectAll = false;
    protected string $redirectUrl = '';
    protected int $attachment = 0;
    protected array $htmlBoundaryParts = [];
    protected array $plainBoundaryParts = [];
    protected string $siteIdentifier = '';

    public function __construct(
        protected CharsetConverter $charsetConverter,
        protected MailRepository $mailRepository,
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
        return $this->mailParts['messageid'];
    }

    /**
     * @param string $messageId
     * @return void
     */
    public function setMessageId(string $messageId): void
    {
        $this->mailParts['messageid'] = $messageId;
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
     * Initializing the MailMessage class and setting the first global variables. Write to log file if it's a cronjob
     *
     * @param int $sendPerCycle Total of recipient in a cycle
     * @param string $backendUserLanguage Language of the user
     *
     * @return void
     */
    public function start(int $sendPerCycle = 50, string $backendUserLanguage = 'en'): void
    {
        $this->setMessageId(MailerUtility::generateMessageId());

            // Mailer engine parameters
        $this->sendPerCycle = $sendPerCycle;
        $this->backendUserLanguage = $backendUserLanguage;
    }

    /**
     * Preparing the Email. Headers are set in global variables
     *
     * @param int $mailUid
     *
     * @return void
     */
    public function prepare(int $mailUid): void
    {
        $mailData = GeneralUtility::makeInstance(SysDmailRepository::class)->findByUid($mailUid);

        $this->mailUid = $mailData['uid'];
        $this->charset = (string)((int)$mailData['type'] === MailType::INTERNAL ? 'utf-8' : $mailData['charset']);
        $this->subject = $this->charsetConverter->conv($mailData['subject'], $this->backendCharset, $this->charset);
        $this->fromName = ($mailData['from_name'] ? $this->charsetConverter->conv($mailData['from_name'], $this->backendCharset, $this->charset) : '');
        $this->fromEmail = $mailData['from_email'];
        $this->replyToName = ($mailData['reply_to_name'] ? $this->charsetConverter->conv($mailData['reply_to_name'], $this->backendCharset, $this->charset) : '');
        $this->replyToEmail = ($mailData['reply_to_email'] ?: '');
        $this->returnPath = (string)($mailData['return_path'] ?? '');
        $this->organisation = ($mailData['organisation'] ? $this->charsetConverter->conv($mailData['organisation'], $this->backendCharset, $this->charset) : '');
        $this->priority = MathUtility::forceIntegerInRange((int)$mailData['priority'], 1, 5);
        $this->mailParts = unserialize(base64_decode($mailData['mail_content']));
        $this->isHtml = (bool)($this->getHtmlContent() ?? false);
        $this->isPlain = (bool)($this->getPlainContent() ?? false);
        $this->flowedFormat = (bool)($mailData['flowed_format'] ?? false);
        $this->includeMedia = (bool)$mailData['include_media'];
        $this->authCodeFieldList = ($mailData['auth_code_fields'] ?: 'uid');
        $this->redirect = (bool)($mailData['redirect'] ?? false);
        $this->redirectAll = (bool)($mailData['redirect_all'] ?? false);
        $this->redirectUrl = (string)($mailData['redirect_url'] ?? '');
        $this->attachment = (int)($mailData['attachment'] ?? 0);

        $this->htmlBoundaryParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->getHtmlContent());
        foreach ($this->htmlBoundaryParts as $bKey => $bContent) {
            $this->htmlBoundaryParts[$bKey] = explode('-->', $bContent, 2);

            // Remove useless HTML comments
            if (substr($this->htmlBoundaryParts[$bKey][0], 1) == 'END') {
                $this->htmlBoundaryParts[$bKey][1] = MailerUtility::removeHtmlComments($this->htmlBoundaryParts[$bKey][1]);
            }

            // Now, analyzing which media files are used in this part of the mail:
            $mediaParts = explode('cid:part', $this->htmlBoundaryParts[$bKey][1]);
            next($mediaParts);
            if (!isset($this->htmlBoundaryParts[$bKey]['mediaList'])) {
                $this->htmlBoundaryParts[$bKey]['mediaList'] = '';
            }
            foreach ($mediaParts as $part) {
                $this->htmlBoundaryParts[$bKey]['mediaList'] .= ',' . strtok($part, '.');
            }
        }
        $this->plainBoundaryParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->getPlainContent());
        foreach ($this->plainBoundaryParts as $bKey => $bContent) {
            $this->plainBoundaryParts[$bKey] = explode('-->', $bContent, 2);
        }
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
            [$contentParts] = MailerUtility::getBoundaryParts($this->plainBoundaryParts);
            $plainContent = implode('', $contentParts);
        }
        $this->setPlainContent($plainContent);

        $htmlContent = '';
        if ($this->getHtmlContent() ?? false) {
            [$contentParts] = MailerUtility::getBoundaryParts($this->htmlBoundaryParts);
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
        $returnCode = SendFormat::NONE;

        foreach ($recipientData as $key => $value) {
            $recipientData[$key] = is_string($value) ? htmlspecialchars($value) : $value;
        }

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipientData['email'] = trim($recipientData['email']);

        if ($recipientData['email']) {
            $midRidId = 'MID' . $this->mailUid . '_' . $tableNameChar . $recipientData['uid'];

            $additionalMarkers = [
                '###SYS_TABLE_NAME###' => $tableNameChar,
                '###SYS_MAIL_ID###' => $this->mailUid,
                '###SYS_AUTHCODE###' => RecipientUtility::stdAuthCode($recipientData, $this->authCodeFieldList),
            ];

            $this->setHtmlContent('');
            if ($this->isHtml && ($recipientData['module_sys_dmail_html'] || $tableNameChar == 'P')) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->htmlBoundaryParts, $recipientData['sys_dmail_categories_list']);
                $tempContent_HTML = implode('', $contentParts);

                if ($mailHasContent) {
                    $tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipientData, $additionalMarkers);
                    $this->setHtmlContent($tempContent_HTML);
                    $returnCode |= SendFormat::HTML;
                }
            }

            // Plain
            $this->setPlainContent('');
            if ($this->isPlain) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->plainBoundaryParts, $recipientData['sys_dmail_categories_list']);
                $plainTextContent = implode('', $contentParts);

                if ($mailHasContent) {
                    $plainTextContent = $this->replaceMailMarkers($plainTextContent, $recipientData, $additionalMarkers);
                    if ($this->redirect || $this->redirectAll) {
                        $plainTextContent = MailerUtility::shortUrlsInPlainText(
                            $plainTextContent,
                            $this->redirectAll ? 0 : 76,
                            $this->redirectUrl
                        );
                    }
                    $this->setPlainContent($plainTextContent);
                    $returnCode |= SendFormat::PLAIN;
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->returnPath = str_replace('###XID###', $midRidId, $this->returnPath);

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
                            $queryBuilder->andWhere($queryBuilder->expr()->notIn('uid', $sentMails));
                        }

                        $statement = $queryBuilder->execute();

                        while ($recipientData = $statement->fetchAssociative()) {
                            $recipientData['sys_dmail_categories_list'] = $this->getListOfRecipientCategories($table, $recipientData['uid']);

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
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table Tablename of the recipient
     * @param int $uid Uid of the recipient
     *
     * @return string        list of categories
     * @throws DBALException
     * @throws Exception
     */
    public function getListOfRecipientCategories(string $table, int $uid): string
    {
        if ($table === 'PLAINLIST') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['categories']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select($relationTable . '.uid_foreign')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_local = ' . $table . '.uid')
            ->where(
                $queryBuilder->expr()->eq($relationTable . '.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq($relationTable . '.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->execute();

        $list = '';
        while ($row = $statement->fetchAssociative()) {
            $list .= $row['uid_foreign'] . ',';
        }

        return rtrim($list, ',');
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
        if ($this->isMailSendToRecipient($mailUid, (int)$recipientData['uid'], $recipientTable) === false) {
            $pt = MailerUtility::getMilliseconds();
            $recipientData = RecipientUtility::normalizeAddress($recipientData);

            // write to dmail_maillog table. if it can be written, continue with sending.
            // if not, stop the script and report error
            $formatSent = 0;

            // try to insert the mail to the mail log repository
            try {
                $logUid = $this->sysDmailMaillogRepository->insertRecord($mailUid, $recipientTable . '_' . $recipientData['uid'], strlen($this->message),
                    MailerUtility::getMilliseconds() - $pt, $formatSent, $recipientData['email']);
            } catch (DBALException $exception) {
                $message = 'Unable to insert log-entry to tx_mail_domain_model_log table. Table full? Mass-Sending stopped. Please delete old records, except of active mailing (mail uid=' . $mailUid . ')';
                $this->logger->critical($message);
                throw new \Exception($message, 1663340700, $exception);
            }

            // Send mail to recipient
            $formatSent = $this->sendPersonalizedMail($recipientData, $recipientTable);

            // try to store the sending return code
            try {
                $this->sysDmailMaillogRepository->updateRecord($logUid, strlen($this->message), MailerUtility::getMilliseconds() - $pt, $formatSent);
            } catch (DBALException $exception) {
                $message = 'Unable to update log-entry in tx_mail_domain_model_log table. Table full? Mass-Sending stopped. Please delete old records, except of active mailing (mail uid=' . $mailUid . ')';
                $this->logger->critical($message);
                throw new \Exception($message, 1663340700, $exception);
            }
        }
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail record
     * @param int $recipientUid Recipient UID
     * @param string $table Recipient table
     *
     * @return bool Number of found records
     * @throws DBALException
     */
    public function isMailSendToRecipient(int $mailUid, int $recipientUid, string $table): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mail_domain_model_log');

        $statement = $queryBuilder
            ->select('uid')
            ->from('tx_mail_domain_model_log')
            ->where($queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($recipientUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('recipient_table', $queryBuilder->createNamedParameter($table)))
            ->andWhere($queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        return (bool)$statement->rowCount();
    }

    /**
     * Set job begin and send a notification to admin if activated in extension settings (notificationJob = 1)
     *
     * @param Mail $mail
     *
     * @return void
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobBegin(Mail $mail): void
    {
        $numberOfRecipients = MailerUtility::getNumberOfRecipients($mail->getUid());

        $mail->setScheduledBegin(new \DateTimeImmutable('now'));
        $mail->setRecipients($numberOfRecipients);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                LanguageUtility::getLL('dmailer_mid') . ' ' . $mail->getUid() . ' ' . LanguageUtility::getLL('dmailer_job_begin'),
                LanguageUtility::getLL('dmailer_job_begin') . ': ' . date('d-m-y h:i:s')
            );
        }
    }

    /**
     *Set job end and send a notification to admin if activated in extension settings (notificationJob = 1)
     *
     * @param Mail $mail
     *
     * @return void
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobEnd(Mail $mail): void
    {
        $numberOfRecipients = MailerUtility::getNumberOfRecipients($mail->getUid());

        $mail->setScheduledEnd(new \DateTimeImmutable('now'));
        $mail->setRecipients($numberOfRecipients);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                LanguageUtility::getLL('dmailer_mid') . ' ' . $mail->getUid() . ' ' . LanguageUtility::getLL('dmailer_job_end'),
                LanguageUtility::getLL('dmailer_job_end') . ': ' . date('d-m-y h:i:s')
            );
        }
    }

    /**
     * @param string $subject
     * @param string $body
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function notifySenderAboutJobState(string $subject, string $body): void
    {
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

        $mail->text($body);
        $mail->send();
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
    public function handleQueue(): void
    {
        $this->notificationJob = (bool)ConfigurationUtility::getExtensionConfiguration('notificationJob');

        if (!is_object(LanguageUtility::getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ?: $this->backendUserLanguage;
            LanguageUtility::getLanguageService()->init(trim($language));
        }

        // always include locallang file
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');

        $pt = MailerUtility::getMilliseconds();

        $this->logger->debug(LanguageUtility::getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));
        $mailToSend = $this->mailRepository->findMailToSend();
        if ($mailToSend instanceof Mail) {
            $this->logger->debug(LanguageUtility::getLL('dmailer_sys_dmail_record') . ' ' . $mailToSend->getUid() . ', \'' . $mailToSend->getSubject() . '\'' . LanguageUtility::getLL('dmailer_processed'));
            $this->prepare($mailToSend->getUid());
            $query_info = unserialize($mailToSend->getQueryInfo());

            if (!$mailToSend->getScheduledBegin()) {
                // Hook to alter the list of recipients
                if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'])) {
                    $queryInfoHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'];
                    if (is_array($queryInfoHook)) {
                        $hookParameters = [
                            'mail' => $mailToSend,
                            'query_info' => &$query_info,
                        ];
                        $hookReference = &$this;
                        foreach ($queryInfoHook as $hookFunction) {
                            GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                        }
                    }
                }
                $this->setJobBegin($mailToSend);
            }

            $finished = !is_array($query_info['id_lists']) || $this->massSend($query_info['id_lists'], $mailToSend->getUid());

            if ($finished) {
                $this->setJobEnd($mailToSend);
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
        if ($this->attachment > 0) {
            $files = MailerUtility::getAttachments($this->mailUid);
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

        if (GeneralUtility::validEmail($this->returnPath)) {
            $mailer->sender($this->returnPath);
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
