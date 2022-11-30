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
use MEDIAESSENZ\Mail\Events\ManipulateMarkersEvent;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Mail\MailMessage;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use pQuery;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

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
    protected string $charset = 'utf-8';
    protected string $subject = '';
    protected string $subjectPrefix = '';
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
    protected array $htmlContentParts = [];
    protected array $plainContentParts = [];
    protected string $siteIdentifier = '';
    protected Site $site;

    public function __construct(
        protected CharsetConverter $charsetConverter,
        protected MailRepository $mailRepository,
        protected LogRepository $logRepository,
        protected RequestFactory $requestFactory,
        protected Context $context,
        protected SiteFinder $siteFinder,
        protected EventDispatcherInterface $eventDispatcher
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
     * @param string $subjectPrefix
     */
    public function setSubjectPrefix(string $subjectPrefix): void
    {
        $this->subjectPrefix = $subjectPrefix;
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
        $this->mail = $this->mailRepository->findByUid($mailUid);
        $this->charset = $this->mail->getType() === MailType::INTERNAL ? 'utf-8' : strtolower($this->mail->getCharset());
        $this->subject = $this->charsetConverter->conv($this->mail->getSubject(), $this->backendCharset, $this->charset);
        $this->fromName = ($this->mail->getFromName() ? $this->charsetConverter->conv($this->mail->getFromName(), $this->backendCharset, $this->charset) : '');
        $this->replyToName = ($this->mail->getReplyToName() ? $this->charsetConverter->conv($this->mail->getReplyToName(), $this->backendCharset, $this->charset) : '');
        $this->organisation = ($this->mail->getOrganisation() ? $this->charsetConverter->conv($this->mail->getOrganisation(), $this->backendCharset, $this->charset) : '');
        $this->priority = MathUtility::forceIntegerInRange($this->mail->getPriority(), 1, 5);
        $this->isHtml = (bool)($this->mail->getHtmlContent() ?? false);
        $this->isPlain = (bool)($this->mail->getPlainContent() ?? false);
        $this->authCodeFieldList = $this->mail->getAuthCodeFields() ?: 'uid';
        $this->attachment = $this->mail->getAttachment()->count();
        $this->htmlContentParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->mail->getHtmlContent());
        foreach ($this->htmlContentParts as $bKey => $bContent) {
            $this->htmlContentParts[$bKey] = explode('-->', $bContent, 2);
            // remove useless HTML comments
            if (substr($this->htmlContentParts[$bKey][0], 1) == 'END') {
                $this->htmlContentParts[$bKey][1] = MailerUtility::removeHtmlComments($this->htmlContentParts[$bKey][1]);
            }
        }
        $this->plainContentParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $this->mail->getPlainContent());
        foreach ($this->plainContentParts as $bKey => $bContent) {
            $this->plainContentParts[$bKey] = explode('-->', $bContent, 2);
        }
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param string $addressList comma separated list of emails
     *
     * @return void
     */
    public function sendSimpleMail(string $addressList): void
    {
        $addressList = str_replace(';', ',', $addressList);
        $recipients = explode(',', $addressList);

        foreach ($recipients as $recipient) {
            $this->sendMailToRecipient(
                $recipient,
                MailerUtility::getContentFromContentPartsMatchingUserCategories($this->htmlContentParts),
                MailerUtility::getContentFromContentPartsMatchingUserCategories($this->plainContentParts)
            );
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

            $recipientCategories = $recipientData['categories'] ?? [];

            $htmlContent = '';
            if ($this->isHtml && (($recipientData['mail_html'] ?? false) || $tableName === 'tx_mail_domain_model_group')) {
                $htmlContent = MailerUtility::getContentFromContentPartsMatchingUserCategories($this->htmlContentParts, $recipientCategories);

                if ($htmlContent) {
                    $htmlContent = $this->replaceMailMarkers($htmlContent, $recipientData, $tableName, $additionalMarkers);
                    $formatSent->set(SendFormat::HTML);
                }
            }

            // Plain
            $plainContent = '';

            if ($this->isPlain) {
                $plainContent = MailerUtility::getContentFromContentPartsMatchingUserCategories($this->plainContentParts, $recipientCategories);

                if ($plainContent) {
                    $plainContent = $this->replaceMailMarkers($plainContent, $recipientData, $tableName, $additionalMarkers);
                    if ($this->mail->isRedirect() || $this->mail->isRedirectAll()) {
                        $plainContent = MailerUtility::shortUrlsInPlainText(
                            $plainContent,
                            $this->mail->isRedirectAll() ? 0 : 76,
                            $this->mail->getRedirectUrl(),
                            $this->site->getLanguageById($this->mail->getSysLanguageUid())->getBase()->getHost() ?: '*'
                        );
                    }
                    $formatSent->set(SendFormat::PLAIN);
                }
            }

            $mailIdentifierHeaderWithoutHash = MailerUtility::buildMailIdentifierHeaderWithoutHash($this->mailUid, $tableName, (int)$recipientData['uid']);
            $this->TYPO3MID = MailerUtility::buildMailIdentifierHeader($mailIdentifierHeaderWithoutHash);

            // todo what is this for?
            $this->mail->setReturnPath(str_replace('###XID###', $mailIdentifierHeaderWithoutHash, $this->mail->getReturnPath()));

            if (($formatSent->get(SendFormat::PLAIN) || $formatSent->get(SendFormat::HTML)) && GeneralUtility::validEmail($recipientData['email'])) {
                $this->sendMailToRecipient(
                    new Address($recipientData['email'], $this->charsetConverter->conv($recipientData['name'], $this->backendCharset, $this->charset)),
                    $htmlContent,
                    $plainContent
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
     * @param string $tableName table or domain model name
     * @param array $markers Existing markers that are mail-specific, not user-specific
     *
     * @return string content with replaced markers
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function replaceMailMarkers(string $content, array $recipient, string $tableName, array $markers): string
    {
        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipientFields'), true);
        if ($addRecipientFields = ConfigurationUtility::getExtensionConfiguration('additionalRecipientFields')) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $addRecipientFields, true));
        }

        foreach ($rowFieldsArray as $substField) {
            if (isset($recipient[$substField])) {
                $markers['###USER_' . $substField . '###'] = $this->charsetConverter->conv($recipient[$substField], $this->backendCharset, $this->charset);
            }
        }

        // PSR-14 event to manipulate markers to add e.g. salutation or other data
        // see MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers for example
        $markers = $this->eventDispatcher->dispatch(new ManipulateMarkersEvent($markers, $recipient, $tableName))->getMarkers();

        return GeneralUtility::makeInstance(MarkerBasedTemplateService::class)->substituteMarkerArray($content, $markers);
    }

    /**
     * Mass send to recipient in the list
     *
     * @return boolean
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function massSend(): bool
    {
        $numberOfSentMails = 0;
        $groupedRecipientIds = $this->mail->getRecipients();
        foreach ($groupedRecipientIds as $recipientTable => $recipientIds) {
            if (is_array($recipientIds)) {
                $numberOfSentMailsOfGroup = 0;

                // get already sent mails
                $sentMails = $this->logRepository->findRecipientsByMailUidAndRecipientTable($this->mail->getUid(), $recipientTable);

                if ($recipientTable === 'tx_mail_domain_model_group') {
                    foreach ($recipientIds as $recipientUid => $recipientData) {
                        // fake uid for csv
                        $recipientUid++;
                        if (!in_array($recipientUid, $sentMails)) {
                            if ($numberOfSentMails >= $this->sendPerCycle) {
                                return false;
                            }
                            $recipientData['uid'] = $recipientUid;
                            $this->sendSingleMailAndAddLogEntry($recipientData, $recipientTable);
                            $numberOfSentMailsOfGroup++;
                            $numberOfSentMails++;
                        }
                    }
                } else {
                    if ($recipientIds) {
                        $model = $this->siteConfiguration['RecipientGroups'][$recipientTable]['model'] ?? false;
                        if ($model || str_contains($recipientTable, 'Domain\\Model')) {
                            $recipientService = GeneralUtility::makeInstance(RecipientService::class);
                            $recipientsData = $recipientService->getRecipientsDataByUidListAndModelName(
                                $recipientIds,
                                $model ?: $recipientTable,
                                ['uid', 'name', 'email', 'categories', 'mail_html'],
                                true,
                                $this->sendPerCycle + 1
                            );
                            foreach ($recipientsData as $recipientData) {
                                $this->sendSingleMailAndAddLogEntry($recipientData, $recipientTable);
                                $numberOfSentMailsOfGroup++;
                                $numberOfSentMails++;
                            }
                        } else {
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($recipientTable);
                            $queryBuilder
                                ->select('*')
                                ->from($recipientTable)
                                ->where($queryBuilder->expr()->in('uid', $queryBuilder->quoteArrayBasedValueListToIntegerList($recipientIds)))
                                ->setMaxResults($this->sendPerCycle + 1);
                            if ($sentMails) {
                                // exclude already sent mails
                                $queryBuilder->andWhere($queryBuilder->expr()->notIn('uid', $queryBuilder->quoteArrayBasedValueListToIntegerList($sentMails)));
                            }

                            $statement = $queryBuilder->execute();

                            while ($recipientData = $statement->fetchAssociative()) {
                                $recipientData['categories'] = $this->getListOfRecipientCategories($recipientTable, $recipientData['uid']);
                                if ($numberOfSentMails >= $this->sendPerCycle) {
                                    return false;
                                }
                                $this->sendSingleMailAndAddLogEntry($recipientData, $recipientTable);
                                $numberOfSentMailsOfGroup++;
                                $numberOfSentMails++;
                            }
                        }
                    }
                }

                $this->logger->debug('Sending ' . $numberOfSentMailsOfGroup . ' mails using records from table ' . $recipientTable);
            }
        }
        return true;
    }


    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table table of the recipient (tt_address or fe_users)
     * @param int $uid Uid of the recipient
     *
     * @return array list of categories
     * @throws DBALException
     * @throws Exception
     */
    public function getListOfRecipientCategories(string $table, int $uid): array
    {
        if ($table === 'tx_mail_domain_model_group') {
            return [];
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

        $recipientCategories = [];
        while ($row = $statement->fetchAssociative()) {
                $recipientCategories[] = (int)$row['uid_local'];
        }

        return $recipientCategories;
    }

    /**
     * Sending the email and write to log.
     *
     * @param array $recipientData Recipient's data array
     * @param string $recipientTable Table name
     *
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function sendSingleMailAndAddLogEntry(array $recipientData, string $recipientTable): void
    {
        if ($this->logRepository->findOneByRecipientUidAndRecipientTableAndMailUid((int)$recipientData['uid'], $recipientTable, $this->mail->getUid()) === false) {
            $parseTime = MailerUtility::getMilliseconds();

            // PSR-14 event dispatcher to manipulate recipient data
            // see MEDIAESSENZ\Mail\EventListener\NormalizeRecipientData for example
            $recipientData = $this->eventDispatcher->dispatch(new ManipulateRecipientEvent($recipientData, $recipientTable))->getRecipientData();

            // write to log table. if it can be written, continue with sending.
            // if not, stop the script and report error
            // try to insert the mail to the mail log repository
            $log = GeneralUtility::makeInstance(Log::class);
            $log->setMail($this->mail);
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
     * Set job begin and send a notification to admin if activated in extension settings (notificationJob = 1)
     *
     * @return void
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobBegin(): void
    {
        $this->mail->setScheduledBegin(new DateTimeImmutable('now'));
        $this->mailRepository->update($this->mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                'Mail Uid ' . $this->mail->getUid() . ' Job begin',
                'Job begin: ' . date('d-m-y h:i:s')
            );
        }
    }

    /**
     *Set job end and send a notification to admin if activated in extension settings (notificationJob = 1)
     *
     * @return void
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function setJobEnd(): void
    {
        $this->mail->setScheduledEnd(new DateTimeImmutable('now'));
        $this->mailRepository->update($this->mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                'Mail Uid ' . $this->mail->getUid() . ' Job end',
                'Job end: ' . date('d-m-y h:i:s')
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

        $this->logger->debug('Invoked at ' . date('h:i:s d-m-Y'));
        $mail = $this->mailRepository->findMailToSend();
        if ($mail instanceof Mail) {
            $this->logger->debug(LanguageUtility::getLL('tx_mail_domain_model_mail') . ' ' . $mail->getUid() . ', \'' . $mail->getSubject() . '\' processed...');
            $this->prepare($mail->getUid());

            if (!$this->mail->getScheduledBegin()) {
                // todo add psr-15 event to manipulate mail before send
                $this->setJobBegin();
            }

            $finished = $this->massSend();

            if ($finished) {
                $this->setJobEnd();
            }
        } else {
            $this->logger->debug('Nothing to do.');
        }

        $parseTime = MailerUtility::getMilliseconds() - $startTime;
        $this->logger->debug('Ending, parsetime ' . $parseTime . ' ms');
    }

    /**
     * Send mail to recipient
     *
     * @param string|Address $recipient The recipient to send the mail to
     * @param string $htmlContent
     * @param string $plainContent
     * @return void
     */
    protected function sendMailToRecipient(Address|string $recipient, string $htmlContent, string $plainContent): void
    {
        /** @var MailMessage $mailMessage */
        $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
        $mailMessage
            ->setSiteIdentifier($this->siteIdentifier)
            ->from(new Address($this->mail->getFromEmail(), $this->fromName))
            ->to($recipient)
            ->subject($this->subjectPrefix . $this->subject)
            ->priority($this->priority);

        if ($this->mail->getReplyToEmail()) {
            $mailMessage->replyTo(new Address($this->mail->getReplyToEmail(), $this->replyToName));
        } else {
            $mailMessage->replyTo(new Address($this->mail->getFromEmail(), $this->fromName));
        }

        if (GeneralUtility::validEmail($this->mail->getReturnPath())) {
            $mailMessage->sender($this->mail->getReturnPath());
        }

        $this->addHtmlPlainTextAndAttachmentsToMailMessage($mailMessage, $htmlContent, $plainContent);

        // setting additional header
        // organization and TYPO3MID
        $header = $mailMessage->getHeaders();
        if ($this->TYPO3MID) {
            $header->addTextHeader(Constants::MAIL_HEADER_IDENTIFIER, $this->TYPO3MID);
        }

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // todo add PSR-14 Event to modify mail headers

        $mailMessage->send();
        unset($mailMessage);
    }

    /**
     * Add html, plaintext and attachments to mail message
     *
     * @param MailMessage $mailMessage
     * @param string $htmlContent
     * @param string $plainContent
     * @return void
     */
    protected function addHtmlPlainTextAndAttachmentsToMailMessage(MailMessage $mailMessage, string $htmlContent = '', string $plainContent = ''): void
    {
        // iterate through the media array and embed them
        if ($htmlContent) {
            if ($this->mail->isIncludeMedia()) {
                // extract all media path from the mail message
                $dom = pQuery::parseStr($htmlContent);
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
                $mailMessage->html($dom->html());
            } else {
                // add html content part to mail
                $mailMessage->html($htmlContent);
            }
        }

        if ($plainContent) {
            // add plain content part to mail
            $mailMessage->text($plainContent);
        }

        // handle FAL attachments
        if ($this->attachment > 0) {
            $files = MailerUtility::getAttachments($this->mailUid);
            /** @var FileReference $file */
            foreach ($files as $file) {
                // $mailMessage->attachFromPath($file->getForLocalProcessing(false));
                $mailMessage->attach($file->getContents(), $file->getName(), $file->getMimeType());
            }
        }
    }
}
