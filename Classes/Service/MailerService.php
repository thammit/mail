<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use DateTimeImmutable;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Log;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Mail\MailMessage;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use pQuery;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\MathUtility;

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
    protected Mail $mail;
    protected int $mailUid = 0;
    protected int $sendPerCycle = 50;
    protected bool $isHtml = false;
    protected bool $isPlain = false;
    protected string $backendUserLanguage = 'default';
    protected bool $isTestMail = false;
    protected string $charset = 'utf-8';
    protected string $subject = '';
    protected string $fromName = '';
    protected string $organisation = '';
    protected string $replyToName = '';
    protected int $priority = 3;
    protected string $authCodeFieldList = '';
    protected string $backendCharset = 'utf-8';
    protected string $message = '';
    protected bool $notificationJob = false;
    protected bool $redirect = false;
    protected string $redirectUrl = '';
    protected int $attachment = 0;
    protected array $htmlBoundaryParts = [];
    protected array $plainBoundaryParts = [];
    protected string $siteIdentifier = '';
    protected Site $site;

    public function __construct(
        protected CharsetConverter $charsetConverter,
        protected MailRepository $mailRepository,
        protected LogRepository $logRepository,
        protected RequestFactory $requestFactory,
        protected Context $context,
        protected SiteFinder $siteFinder,
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
     * @throws SiteNotFoundException
     */
    public function setSiteIdentifier(string $siteIdentifier): void
    {
        $this->siteIdentifier = $siteIdentifier;
        $this->site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
    }

    /**
     * @param bool $isTestMail
     */
    public function setTestMail(bool $isTestMail): void
    {
        $this->isTestMail = $isTestMail;
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
     * Initializing the MailMessage class and setting the first global variables. Write to log file if it's a cronjob
     *
     * @param int $sendPerCycle Total of recipient in a cycle
     * @param string $backendUserLanguage Language of the user
     *
     * @return void
     */
    public function start(int $sendPerCycle = 50, string $backendUserLanguage = 'en'): void
    {
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
        $this->mailUid = $mailUid;
        /** @var Mail $mail */
        $this->mail = $this->mailRepository->findByUid($mailUid);

        $this->charset = $this->mail->getType() === MailType::INTERNAL ? 'utf-8' : $this->mail->getCharset();
        $this->subject = $this->charsetConverter->conv($this->mail->getSubject(), $this->backendCharset, $this->charset);
        $this->fromName = ($this->mail->getFromName() ? $this->charsetConverter->conv($this->mail->getFromName(), $this->backendCharset, $this->charset) : '');
        $this->replyToName = ($this->mail->getReplyToName() ? $this->charsetConverter->conv($this->mail->getReplyToName(), $this->backendCharset, $this->charset) : '');
        $this->organisation = ($this->mail->getOrganisation() ? $this->charsetConverter->conv($this->mail->getOrganisation(), $this->backendCharset, $this->charset) : '');
        $this->priority = MathUtility::forceIntegerInRange($this->mail->getPriority(), 1, 5);
        $this->isHtml = (bool)($this->mail->getHtmlContent() ?? false);
        $this->isPlain = (bool)($this->mail->getPlainContent() ?? false);
        $this->authCodeFieldList = $this->mail->getAuthCodeFields() ?: 'uid';
        $this->attachment = $this->mail->getAttachment()->count();
        $this->htmlBoundaryParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->mail->getHtmlContent());

        foreach ($this->htmlBoundaryParts as $bKey => $bContent) {
            $this->htmlBoundaryParts[$bKey] = explode('-->', $bContent, 2);

            // remove useless HTML comments
            if (substr($this->htmlBoundaryParts[$bKey][0], 1) == 'END') {
                $this->htmlBoundaryParts[$bKey][1] = MailerUtility::removeHtmlComments($this->htmlBoundaryParts[$bKey][1]);
            }

            // analyzing which media files are used in this part of the mail:
            $mediaParts = explode('cid:part', $this->htmlBoundaryParts[$bKey][1]);
            next($mediaParts);
            if (!isset($this->htmlBoundaryParts[$bKey]['mediaList'])) {
                $this->htmlBoundaryParts[$bKey]['mediaList'] = '';
            }
            foreach ($mediaParts as $part) {
                $this->htmlBoundaryParts[$bKey]['mediaList'] .= ',' . strtok($part, '.');
            }
        }
        $this->plainBoundaryParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->mail->getPlainContent());
        foreach ($this->plainBoundaryParts as $bKey => $bContent) {
            $this->plainBoundaryParts[$bKey] = explode('-->', $bContent, 2);
        }
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param string $addressList list of recipient address, comma list of emails
     *
     * @return void
     */
    public function sendSimpleMail(string $addressList): void
    {
        $plainContent = '';
        if ($this->mail->getPlainContent() ?? false) {
            [$contentParts] = MailerUtility::getBoundaryParts($this->plainBoundaryParts);
            $plainContent = implode('', $contentParts);
        }
        $this->mail->setPlainContent($plainContent);

        $htmlContent = '';
        if ($this->mail->getHtmlContent() ?? false) {
            [$contentParts] = MailerUtility::getBoundaryParts($this->htmlBoundaryParts);
            $htmlContent = implode('', $contentParts);
        }
        $this->mail->setHtmlContent($htmlContent);

        $recipients = explode(',', $addressList);

        foreach ($recipients as $recipient) {
            $this->sendMailToRecipient($recipient);
        }
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param array $recipientData Recipient's data array
     * @param string $tableName Table name, from which the recipient come from
     *
     * @return SendFormat Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function sendPersonalizedMail(array $recipientData, string $tableName): SendFormat
    {
        $formatSent = new SendFormat(SendFormat::NONE);

        foreach ($recipientData as $key => $value) {
            $recipientData[$key] = is_string($value) ? htmlspecialchars($value) : $value;
        }

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipientData['email'] = trim($recipientData['email']);

        if ($recipientData['email']) {

            $additionalMarkers = [
                '###SYS_TABLE_NAME###' => $tableName,
                '###SYS_MAIL_ID###' => $this->mailUid,
                '###SYS_AUTHCODE###' => RecipientUtility::stdAuthCode($recipientData, $this->authCodeFieldList),
            ];

            $this->mail->setHtmlContent('');
            if ($this->isHtml && (($recipientData['accepts_html'] ?? false) || $tableName === 'tx_mail_domain_model_group')) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->htmlBoundaryParts, ($recipientData['categories'] ?? ''));

                if ($mailHasContent) {
                    $this->mail->setHtmlContent($this->replaceMailMarkers(implode('', $contentParts), $recipientData, $additionalMarkers));
                    $formatSent->set(SendFormat::HTML);
                }
            }

            // Plain
            $this->mail->setPlainContent('');
            if ($this->isPlain) {
                [$contentParts, $mailHasContent] = MailerUtility::getBoundaryParts($this->plainBoundaryParts, ($recipientData['categories'] ?? ''));

                if ($mailHasContent) {
                    $plainTextContent = $this->replaceMailMarkers(implode('', $contentParts), $recipientData, $additionalMarkers);
                    if ($this->mail->isRedirect() || $this->mail->isRedirectAll()) {
                        $plainTextContent = MailerUtility::shortUrlsInPlainText(
                            $plainTextContent,
                            $this->mail->isRedirectAll() ? 0 : 76,
                            $this->mail->getRedirectUrl(),
                            $this->site->getLanguageById($this->mail->getSysLanguageUid())->getBase()->getHost() ?: '*'
                        );
                    }
                    $this->mail->setPlainContent($plainTextContent);
                    $formatSent->set(SendFormat::PLAIN);
                }
            }

            $this->TYPO3MID = MailerUtility::buildMailIdentifierHeader($this->mailUid, $tableName, $recipientData['uid']);

            // todo what is this for?
            $this->mail->setReturnPath(str_replace('###XID###', explode('-', $this->TYPO3MID)[0], $this->mail->getReturnPath()));

            if (($formatSent->get(SendFormat::PLAIN) || $formatSent->get(SendFormat::HTML)) && GeneralUtility::validEmail($recipientData['email'])) {
                $this->sendMailToRecipient(
                    new Address($recipientData['email'], $this->charsetConverter->conv($recipientData['name'], $this->backendCharset, $this->charset))
                );
            }

        }

        return $formatSent;
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param string $content The HTML or plaintext part
     * @param array $recipient Recipient's data array
     * @param array $markers Existing markers that are mail-specific, not user-specific
     *
     * @return string content with replaced markers
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function replaceMailMarkers(string $content, array $recipient, array $markers): string
    {
        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipFields'), true);
        if ($addRecipientFields = ConfigurationUtility::getExtensionConfiguration('addRecipFields')) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $addRecipientFields, true));
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
     * Mass send to recipient in the list
     *
     * @param array $groupedRecipientIds
     * @param int $mailUid
     * @return boolean
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function massSend(array $groupedRecipientIds, int $mailUid): bool
    {
        $numberOfSentMails = 0;
        $finished = true;
        foreach ($groupedRecipientIds as $table => $listArr) {
            if (is_array($listArr)) {
                $numberOfSentMailsOfGroup = 0;

                // get already sent mails
                $sentMails = $this->logRepository->findRecipientsByMailUidAndRecipientTable($mailUid, $table);
                if ($table === 'tx_mail_domain_model_group') {
                    foreach ($listArr as $kval => $recipientData) {
                        $kval++;
                        if (!in_array($kval, $sentMails)) {
                            if ($numberOfSentMails >= $this->sendPerCycle) {
                                $finished = false;
                                break;
                            }
                            $recipientData['uid'] = $kval;
                            $this->sendSingleMailAndAddLogEntry($mailUid, $recipientData, $table);
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
                            $recipientData['categories'] = $this->getListOfRecipientCategories($table, $recipientData['uid']);

                            if ($numberOfSentMails >= $this->sendPerCycle) {
                                $finished = false;
                                break;
                            }

                            // We are NOT finished!
                            $this->sendSingleMailAndAddLogEntry($mailUid, $recipientData, $table);
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
     * @param string $table table of the recipient (tt_address or fe_users)
     * @param int $uid Uid of the recipient
     *
     * @return string        list of categories
     * @throws DBALException
     * @throws Exception
     */
    public function getListOfRecipientCategories(string $table, int $uid): string
    {
        if ($table === 'tx_mail_domain_model_group') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['categories']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select($relationTable . '.uid_local')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_foreign = ' . $table . '.uid')
            ->where(
                $queryBuilder->expr()->eq($relationTable . '.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq($relationTable . '.uid_foreign', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->execute();

        $list = '';
        while ($row = $statement->fetchAssociative()) {
            $list .= $row['uid_local'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * Sending the email and write to log.
     *
     * @param int $mailUid
     * @param array $recipientData Recipient's data array
     * @param string $recipientTable Table name
     *
     * @return void
     * @throws DBALException
     * @throws \TYPO3\CMS\Core\Exception
     * @throws \Exception
     */
    protected function sendSingleMailAndAddLogEntry(int $mailUid, array $recipientData, string $recipientTable): void
    {
        if ($this->isMailSendToRecipient($mailUid, (int)$recipientData['uid'], $recipientTable) === false) {
            $parseTime = MailerUtility::getMilliseconds();
            $recipientData = RecipientUtility::normalizeAddress($recipientData);

            // write to log table. if it can be written, continue with sending.
            // if not, stop the script and report error
            // try to insert the mail to the mail log repository
            $mail = $this->mailRepository->findByUid($mailUid);
            $log = GeneralUtility::makeInstance(Log::class);
            $log->setMail($mail);
            $log->setRecipientTable($recipientTable);
            $log->setRecipientUid($recipientData['uid']);
            $log->setEmail($recipientData['email']);
            $this->logRepository->add($log);
            $this->logRepository->persist();

            // Send mail to recipient
            $formatSent = $this->sendPersonalizedMail($recipientData, $recipientTable);

            // try to store the sending return code
            $log->setFormatSent($formatSent);
            $log->setParseTime(MailerUtility::getMilliseconds() - $parseTime);
            $this->logRepository->update($log);
            $this->logRepository->persist();
        }
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mailUid
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
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobBegin(Mail $mail): void
    {
        $mail->setScheduledBegin(new DateTimeImmutable('now'));
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
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobEnd(Mail $mail): void
    {
        $mail->setScheduledEnd(new DateTimeImmutable('now'));
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
     */
    protected function notifySenderAboutJobState(string $subject, string $body): void
    {
        $fromName = $this->charsetConverter->conv($this->fromName, $this->charset, $this->backendCharset) ?? '';
        $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
        $mailMessage
            ->setSiteIdentifier($this->siteIdentifier)
            ->setTo($this->mail->getFromEmail(), $fromName)
            ->setFrom($this->mail->getFromEmail(), $fromName)
            ->setSubject($subject);

        if ($this->mail->getReplyToEmail() !== '') {
            $mailMessage->setReplyTo($this->mail->getReplyToEmail());
        }

        $mailMessage->text($body);
        $mailMessage->send();
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
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function handleQueue(): void
    {
        $this->notificationJob = (bool)ConfigurationUtility::getExtensionConfiguration('notificationJob');

        if (!is_object(LanguageUtility::getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mail']['cron_language'] ?: $this->backendUserLanguage;
            LanguageUtility::getLanguageService()->init(trim($language));
        }

        // always include locallang file
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');

        $startTime = MailerUtility::getMilliseconds();

        $this->logger->debug(LanguageUtility::getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));
        $mail = $this->mailRepository->findMailToSend();
        if ($mail instanceof Mail) {
            $this->logger->debug(LanguageUtility::getLL('dmailer_sys_dmail_record') . ' ' . $mail->getUid() . ', \'' . $mail->getSubject() . '\'' . LanguageUtility::getLL('dmailer_processed'));
            $this->prepare($mail->getUid());
            $recipients = $mail->getRecipients();

            if (!$mail->getScheduledBegin()) {
                // todo add psr-15 event to manipulate mail before send
                $this->setJobBegin($mail);
            }

            $finished = $this->massSend($recipients, $mail->getUid());

            if ($finished) {
                $this->setJobEnd($mail);
            }
        } else {
            $this->logger->debug(LanguageUtility::getLL('dmailer_nothing_to_do'));
        }

        $parseTime = MailerUtility::getMilliseconds() - $startTime;
        $this->logger->debug(LanguageUtility::getLL('dmailer_ending') . ' ' . $parseTime . ' ms');
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the mail body
     *
     * @return void
     * @var MailMessage $mailMessage Mailer Message Object
     */
    protected function setContent(MailMessage $mailMessage): void
    {
        // iterate through the media array and embed them
        if ($this->mail->getHtmlContent()) {
            if ($this->mail->isIncludeMedia()) {
                // extract all media path from the mail message
                $dom = pQuery::parseStr($this->mail->getHtmlContent());
                /** @var pQuery\IQuery $element */
                foreach($dom->query('img[!do_not_embed]') as $element) {
                    $absoluteImagePath = MailerUtility::absRef($element->attr('src'), $this->mail->getRedirectUrl());
                    // change image src to absolute path in case fetch and embed fails
                    $element->attr('src', $absoluteImagePath);
                    // fetch image from absolute url
                    $response = $this->requestFactory->request($absoluteImagePath);
                    if ($response->getStatusCode() === 200) {
                        $baseName = basename($absoluteImagePath);
                        // embed image into mail
                        $mailMessage->embed($response->getBody()->getContents(), $baseName, $response->getHeaderLine('Content-Type'));
                        // set image src to embed cid
                        $element->attr('src',  'cid:' . $baseName);
                    }
                }
                $this->mail->setHtmlContent($dom->html());
            }

            // add html content part to mail
            $mailMessage->html($this->mail->getHtmlContent());
        }

        if ($plainContent = $this->mail->getPlainContent()) {
            // add plain content part to mail
            $mailMessage->text($plainContent);
        }

        // handle FAL attachments
        if ($this->attachment > 0) {
            $files = MailerUtility::getAttachments($this->mailUid);
            /** @var FileReference $file */
            foreach ($files as $file) {
                $mailMessage->attachFromPath($file->getForLocalProcessing(false));
            }
        }
    }

    /**
     * Send of the email using php mail function.
     *
     * @param string|Address $recipient The recipient to send the mail to
     * @return void
     */
    protected function sendMailToRecipient(Address|string $recipient): void
    {
        /** @var MailMessage $mailer */
        $mailer = GeneralUtility::makeInstance(MailMessage::class);
        $mailer
            ->setSiteIdentifier($this->siteIdentifier)
            ->from(new Address($this->mail->getFromEmail(), $this->fromName))
            ->to($recipient)
            ->subject($this->subject)
            ->priority($this->priority);

        if ($this->mail->getReplyToEmail()) {
            $mailer->replyTo(new Address($this->mail->getReplyToEmail(), $this->replyToName));
        } else {
            $mailer->replyTo(new Address($this->mail->getFromEmail(), $this->fromName));
        }

        if (GeneralUtility::validEmail($this->mail->getReturnPath())) {
            $mailer->sender($this->mail->getReturnPath());
        }

        $this->setContent($mailer);

        // setting additional header
        // organization and TYPO3MID
        $header = $mailer->getHeaders();
        if ($this->TYPO3MID) {
            $header->addTextHeader(Constants::MAIL_HEADER_IDENTIFIER, $this->TYPO3MID);
        }

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // todo add PSR-14 Event to modify mail headers

        $mailer->send();
        unset($mailer);
    }

}
