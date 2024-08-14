<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use DateTimeImmutable;
use DOMElement;
use JsonException;
use Masterminds\HTML5;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Model\Log;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Events\AdditionalMailHeadersEvent;
use MEDIAESSENZ\Mail\Events\ManipulateMarkersEvent;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Events\ScheduledSendBegunEvent;
use MEDIAESSENZ\Mail\Events\ScheduledSendFinishedEvent;
use MEDIAESSENZ\Mail\Type\Enumeration\CategoryFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\MailStatus;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Mail\MailMessage;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception;
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
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class MailerService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * special header to identify returned mail
     */
    protected string $TYPO3MID = '';
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
    protected bool $notificationJob = false;
    protected int $attachment = 0;
    protected array $htmlContentParts = [];
    protected array $plainContentParts = [];
    protected string $siteIdentifier = '';
    protected array $recipientSources = [];
    protected Site $site;

    public function __construct(
        protected CharsetConverter         $charsetConverter,
        protected MailRepository           $mailRepository,
        protected LogRepository            $logRepository,
        protected RequestFactory           $requestFactory,
        protected Context                  $context,
        protected SiteFinder               $siteFinder,
        protected EventDispatcherInterface $eventDispatcher
    )
    {
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
        $this->recipientSources = ConfigurationUtility::getRecipientSources($this->site->getConfiguration());
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
     * @param Mail $mail
     *
     * @return void
     */
    public function prepare(Mail $mail): void
    {
        $this->charset = $mail->getType() === MailType::INTERNAL ? 'utf-8' : strtolower($mail->getCharset());
        $this->subject = $this->charsetConverter->conv($mail->getSubject(), $this->backendCharset, $this->charset);
        $this->fromName = ($mail->getFromName() ? $this->charsetConverter->conv($mail->getFromName(), $this->backendCharset, $this->charset) : '');
        $this->replyToName = ($mail->getReplyToName() ? $this->charsetConverter->conv($mail->getReplyToName(), $this->backendCharset, $this->charset) : '');
        $this->organisation = ($mail->getOrganisation() ? $this->charsetConverter->conv($mail->getOrganisation(), $this->backendCharset, $this->charset) : '');
        $this->priority = MathUtility::forceIntegerInRange($mail->getPriority(), 1, 5);
        $this->isHtml = (bool)($mail->getHtmlContent() ?? false);
        $this->isPlain = (bool)($mail->getPlainContent() ?? false);
        $this->authCodeFieldList = $mail->getAuthCodeFields() ?: 'uid';
        $this->attachment = $mail->getAttachment()->count();
        $this->htmlContentParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $mail->getHtmlContent());
        foreach ($this->htmlContentParts as $bKey => $bContent) {
            $this->htmlContentParts[$bKey] = explode('-->', $bContent, 2);
            // remove useless HTML comments
            if (substr($this->htmlContentParts[$bKey][0], 1) == 'END') {
                $this->htmlContentParts[$bKey][1] = MailerUtility::removeHtmlComments($this->htmlContentParts[$bKey][1]);
            }
        }
        $this->plainContentParts = explode('<!--' . Constants::CONTENT_SECTION_BOUNDARY, '_END-->' . $mail->getPlainContent());
        foreach ($this->plainContentParts as $bKey => $bContent) {
            $this->plainContentParts[$bKey] = explode('-->', $bContent, 2);
        }
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param Mail $mail
     * @param string $addressList comma separated list of emails
     *
     * @return void
     */
    public function sendSimpleMail(Mail $mail, string $addressList): void
    {
        $addressList = str_replace(';', ',', $addressList);
        $recipients = explode(',', $addressList);

        foreach ($recipients as $recipient) {
            $this->sendMailToRecipient(
                $mail,
                $recipient,
                MailerUtility::getContentFromContentPartsMatchingUserCategories($this->htmlContentParts),
                MailerUtility::getContentFromContentPartsMatchingUserCategories($this->plainContentParts)
            );
        }
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param Mail $mail
     * @param array $recipientData Recipient data
     * @param string $recipientSourceIdentifier Recipient source identifier
     *
     * @return SendFormat Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function sendPersonalizedMail(Mail $mail, array $recipientData, string $recipientSourceIdentifier): SendFormat
    {
        $formatSent = new SendFormat(SendFormat::NONE);

        foreach ($recipientData as $key => $value) {
            $recipientData[$key] = is_string($value) ? htmlspecialchars($value) : $value;
        }

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipientData['email'] = trim($recipientData['email']);

        if ($recipientData['email'] && GeneralUtility::validEmail($recipientData['email'])) {

            $recipientCategories = $recipientData['categories'] ?? [];

            $htmlContent = '';
            if ($this->isHtml && (($recipientData['mail_html'] ?? false) || ($this->recipientSources[$recipientSourceIdentifier]['forceHtmlMail'] ?? false))) {
                $htmlContent = MailerUtility::getContentFromContentPartsMatchingUserCategories($this->htmlContentParts, $recipientCategories);

                if ($htmlContent) {
                    $htmlContent = $this->replaceMailMarkers($mail, $htmlContent, $recipientData, $recipientSourceIdentifier);
                    $formatSent->set(SendFormat::HTML);
                }
            }

            // Plain
            $plainContent = '';

            if ($this->isPlain) {
                $plainContent = MailerUtility::getContentFromContentPartsMatchingUserCategories($this->plainContentParts, $recipientCategories);

                if ($plainContent) {
                    $plainContent = $this->replaceMailMarkers($mail, $plainContent, $recipientData, $recipientSourceIdentifier);
                    if ($mail->isRedirect() || $mail->isRedirectAll()) {
                        $plainContent = MailerUtility::shortUrlsInPlainText(
                            $plainContent,
                            $mail,
                            $this->site->getLanguageById($mail->getSysLanguageUid())->getBase()->getHost() ?: '*'
                        );
                    }
                    $formatSent->set(SendFormat::PLAIN);
                }
            }

            $mailIdentifierHeaderWithoutHash = MailerUtility::buildMailIdentifierHeaderWithoutHash($mail->getUid(), $recipientSourceIdentifier, (int)$recipientData['uid']);
            $this->TYPO3MID = MailerUtility::buildMailIdentifierHeader($mailIdentifierHeaderWithoutHash);

            $mail->setReturnPath(str_replace('###XID###', $mailIdentifierHeaderWithoutHash, $mail->getReturnPath()));

            if ($formatSent->get(SendFormat::PLAIN) || $formatSent->get(SendFormat::HTML)) {
                $this->sendMailToRecipient(
                    $mail,
                    new Address($recipientData['email'], $this->charsetConverter->conv($recipientData['name'] ?? '', $this->backendCharset, $this->charset)),
                    $htmlContent,
                    $plainContent
                );
            } else {
                // todo: no mail to user -> log for report
            }
        }

        return $formatSent;
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param Mail $mail The mail
     * @param string $content The HTML or plaintext part
     * @param array $recipient Recipient data
     * @param string $recipientSourceIdentifier Recipient source identifier
     *
     * @return string content with replaced markers
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function replaceMailMarkers(Mail $mail, string $content, array $recipient, string $recipientSourceIdentifier): string
    {
        $markers = [
            '###MAIL_RECIPIENT_SOURCE###' => $recipientSourceIdentifier,
            '###MAIL_ID###' => $mail->getUid(),
            '###MAIL_AUTHCODE###' => RecipientUtility::stdAuthCode($recipient, $this->authCodeFieldList),
        ];

        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipientFields'), true);
        if ($additionalRecipientFields = ConfigurationUtility::getExtensionConfiguration('additionalRecipientFields')) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $additionalRecipientFields, true));
        }

        foreach ($rowFieldsArray as $substField) {
            if ($recipient[$substField] ?? false) {
                $markers['###USER_' . $substField . '###'] = $this->charsetConverter->conv((string)$recipient[$substField], $this->backendCharset, $this->charset);
            }
        }

        // PSR-14 event to manipulate markers to add e.g. salutation or other data
        // see MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers for example
        $markers = $this->eventDispatcher->dispatch(
            new ManipulateMarkersEvent(
                $markers,
                $recipient,
                $this->recipientSources[$recipientSourceIdentifier]
            )
        )->getMarkers();

        return GeneralUtility::makeInstance(MarkerBasedTemplateService::class)->substituteMarkerArray(
            $content,
            $markers,
            '',
            false,
            (bool)(ConfigurationUtility::getExtensionConfiguration('deleteUnusedMarkers') ?? false)
        );
    }

    /**
     * Mass send to recipient in the list
     * returns true if sending is completed
     *
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws JsonException
     * @throws Exception
     */
    protected function massSend(Mail $mail): void
    {
        $recipientSourceIdentifier = '';
        $recipients = $mail->getRecipients();
        $recipientsHandled = $mail->getRecipientsHandled();
        $numberOfSentMails = 0;
        foreach ($recipients as $recipientSourceIdentifier => $recipientIds) {
            if (!$recipientIds) {
                continue;
            }

            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;

            if (!$recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                $this->logger->debug('No recipient source configuration found for ' . $recipientSourceIdentifier);
                continue;
            }

            $recipientIds = array_slice($recipientIds, 0, $this->sendPerCycle);

            switch (true) {
                case $recipientSourceConfiguration->isTableSource():
                    $table = $recipientSourceConfiguration->table;
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                    $queryResult = $queryBuilder
                        ->select('*')
                        ->from($table)
                        ->where($queryBuilder->expr()->in('uid', $queryBuilder->quoteArrayBasedValueListToIntegerList($recipientIds)))
                        ->executeQuery();

                    while ($recipientData = $queryResult->fetchAssociative()) {
                        $recipientData['categories'] = RecipientUtility::getListOfRecipientCategories($table, $recipientData['uid']);
                        $this->sendSingleMailAndAddLogEntry($mail, $recipientData, $recipientSourceIdentifier);
                        $recipients[$recipientSourceIdentifier] = array_filter($recipients[$recipientSourceIdentifier] ?? [], fn($item) => $item !== $recipientData['uid']);
                        $recipientsHandled[$recipientSourceIdentifier][] = (int)$recipientData['uid'];
                        $numberOfSentMails++;
                    }
                    break;
                case $recipientSourceConfiguration->isModelSource():
                    $recipientService = GeneralUtility::makeInstance(RecipientService::class);
                    $recipientsData = $recipientService->getRecipientsDataByUidListAndModelName(
                        $recipientIds,
                        $recipientSourceConfiguration->model,
                        ['uid', 'name', 'email', 'categories', 'mail_html'],
                        CategoryFormat::UIDS
                    );
                    foreach ($recipientsData as $recipientData) {
                        $this->sendSingleMailAndAddLogEntry($mail, $recipientData, $recipientSourceIdentifier);
                        $recipients[$recipientSourceIdentifier] = array_filter($recipients[$recipientSourceIdentifier] ?? [], fn($item) => $item !== $recipientData['uid']);
                        $recipientsHandled[$recipientSourceIdentifier][] = (int)$recipientData['uid'];
                        $numberOfSentMails++;
                    }
                    break;
                case $recipientSourceConfiguration->isCsvOrPlain():
                    [$table, $groupUid] = explode(':', $recipientSourceIdentifier);
                    foreach ($recipientIds as $recipientUid => $recipientData) {
                        // fake uid for csv
                        $recipientData['uid'] = $recipientUid + 1;
                        $recipientData['categories'] = RecipientUtility::getListOfRecipientCategories($table, (int)$groupUid);
                        $this->sendSingleMailAndAddLogEntry($mail, $recipientData, $recipientSourceIdentifier);
                        $recipients[$recipientSourceIdentifier] = array_filter($recipients[$recipientSourceIdentifier] ?? [], fn($item) => $item !== $recipientData['uid']);
                        $recipientsHandled[$recipientSourceIdentifier][] = (int)$recipientData['uid'];
                        $numberOfSentMails++;
                    }
                    break;
                case $recipientSourceConfiguration->isCsvFile():
                case $recipientSourceConfiguration->isService():
                    // todo
                    break;
            }
            if (!$recipients[$recipientSourceIdentifier]) {
                unset($recipients[$recipientSourceIdentifier]);
            }
        }

        if ($numberOfSentMails && $recipientSourceIdentifier) {
            $this->logger->debug('Sent ' . $numberOfSentMails . ' mails to user of recipient source ' . $recipientSourceIdentifier);
        }

        $mail->setRecipients($recipients, false);
        $mail->setRecipientsHandled($recipientsHandled);

        $this->mailRepository->update($mail);
        $this->mailRepository->persist();
    }

    /**
     * Sending the email and write to log.
     *
     * @param Mail $mail
     * @param array $recipientData Recipient's data array
     * @param string $recipientSourceIdentifier Recipient source identifier
     *
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    protected function sendSingleMailAndAddLogEntry(Mail $mail, array $recipientData, string $recipientSourceIdentifier): void
    {
        if ($this->logRepository->findOneByRecipientUidAndRecipientSourceIdentifierAndMailUid((int)$recipientData['uid'], $recipientSourceIdentifier, $mail->getUid()) === false) {
            $parseTime = MailerUtility::getMilliseconds();

            // PSR-14 event dispatcher to manipulate recipient data
            // see MEDIAESSENZ\Mail\EventListener\NormalizeRecipientData for example
            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
            if ($recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                $recipientData = $this->eventDispatcher->dispatch(new ManipulateRecipientEvent($recipientData, $recipientSourceConfiguration))->getRecipientData();
            }

            // Add mail log entry
            $log = GeneralUtility::makeInstance(Log::class);
            $log->setMail($mail);
            $log->setRecipientSource($recipientSourceIdentifier);
            $log->setRecipientUid((int)$recipientData['uid']);
            $log->setEmail($recipientData['email']);
            $this->logRepository->add($log);
            $this->logRepository->persist();

            // Send mail to recipient
            $formatSent = $this->sendPersonalizedMail($mail, $recipientData, $recipientSourceIdentifier);

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
     * @param Mail $mail
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function setJobBegin(Mail $mail): void
    {
        $mail->setScheduledBegin(new DateTimeImmutable('now'));
        $mail->setStatus(MailStatus::SENDING);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                $mail,
                'Mail Uid ' . $mail->getUid() . ' Job begin',
                'Job begin: ' . date('d-m-y h:i:s')
            );
        }
    }

    /**
     * Set job end and send a notification to admin if activated in extension settings (notificationJob = 1)
     *
     * @param Mail $mail
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function setJobEnd(Mail $mail): void
    {
        $mail->setScheduledEnd(new DateTimeImmutable('now'));
        $mail->setStatus(MailStatus::SENT);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        if ($this->notificationJob === true) {
            $this->notifySenderAboutJobState(
                $mail,
                'Mail Uid ' . $mail->getUid() . ' Job end',
                'Job end: ' . date('d-m-y h:i:s')
            );
        }
    }

    /**
     * @param Mail $mail
     * @param string $subject
     * @param string $body
     */
    protected function notifySenderAboutJobState(Mail $mail, string $subject, string $body): void
    {
        $fromName = $this->charsetConverter->conv($this->fromName, $this->charset, $this->backendCharset) ?? '';
        $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
        $mailMessage
            ->setSiteIdentifier($this->siteIdentifier)
            ->setTo($mail->getFromEmail(), $fromName)
            ->setFrom($mail->getFromEmail(), $fromName)
            ->setSubject($subject);

        if ($mail->getReplyToEmail() !== '') {
            $mailMessage->setReplyTo($mail->getReplyToEmail());
        }

        $mailMessage->text($body);
        $mailMessage->send();
    }

    /**
     * @return void
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws JsonException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
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
            $this->prepare($mail);

            if (!$mail->getScheduledBegin()) {
                // PSR-14 event to manipulate mail before scheduled send begins
                $this->eventDispatcher->dispatch(
                    new ScheduledSendBegunEvent($mail)
                );
                $this->setJobBegin($mail);
            }

            $this->massSend($mail);

            if ($mail->isSent()) {
                // PSR-14 event to manipulate mail after scheduled send finished
                $this->eventDispatcher->dispatch(
                    new ScheduledSendFinishedEvent($mail)
                );
                $this->setJobEnd($mail);
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
     * @param Mail $mail
     * @param string|Address $recipient The recipient to send the mail to
     * @param string $htmlContent
     * @param string $plainContent
     * @return void
     */
    protected function sendMailToRecipient(Mail $mail, Address|string $recipient, string $htmlContent, string $plainContent): void
    {
        /** @var MailMessage $mailMessage */
        $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
        $mailMessage
            ->setSiteIdentifier($this->siteIdentifier)
            ->from(new Address($mail->getFromEmail(), $this->fromName))
            ->to($recipient)
            ->subject($this->subjectPrefix . $this->subject)
            ->priority($this->priority);

        if ($mail->getReplyToEmail()) {
            $mailMessage->replyTo(new Address($mail->getReplyToEmail(), $this->replyToName));
        } else {
            $mailMessage->replyTo(new Address($mail->getFromEmail(), $this->fromName));
        }

        if (GeneralUtility::validEmail($mail->getReturnPath())) {
            $mailMessage->sender($mail->getReturnPath());
        }

        $this->addHtmlPlainTextAndAttachmentsToMailMessage($mail, $mailMessage, $htmlContent, $plainContent);

        // setting additional header
        // organization and TYPO3MID
        $header = $mailMessage->getHeaders();
        if ($this->TYPO3MID) {
            $header->addTextHeader(Constants::MAIL_HEADER_IDENTIFIER, $this->TYPO3MID);
        }

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // PSR-14 event to modify mail headers
        $this->eventDispatcher->dispatch(
            new AdditionalMailHeadersEvent($header, $mail, $this->TYPO3MID, $this->organisation, $this->siteIdentifier)
        )->getHeaders();


        $mailMessage->send();
        unset($mailMessage);
    }

    /**
     * Add html, plaintext and attachments to mail message
     *
     * @param Mail $mail
     * @param MailMessage $mailMessage
     * @param string $htmlContent
     * @param string $plainContent
     * @return void
     */
    protected function addHtmlPlainTextAndAttachmentsToMailMessage(Mail $mail, MailMessage $mailMessage, string $htmlContent = '', string $plainContent = ''): void
    {
        // iterate through the media array and embed them
        if ($htmlContent) {
            if ($mail->isIncludeMedia()) {
                // extract all media path from the mail message
                $html = new HTML5();
                $domDocument = $html->loadHTML($htmlContent);
                $imageElements = $domDocument->getElementsByTagName('img');
                /** @var DOMElement $imageElement */
                foreach ($imageElements as $imageElement) {
                    if (!$imageElement->hasAttribute('data-do-not-embed')) {
                        $absoluteImagePath = MailerUtility::absRef($imageElement->getAttribute('src'), $mail->getRedirectUrl());
                        // change image src to absolute path in case fetch and embed fails
                        $imageElement->setAttribute('src', $absoluteImagePath);
                        // fetch image from absolute url
                        $response = $this->requestFactory->request($absoluteImagePath);
                        if ($response->getStatusCode() === 200) {
                            $baseName = basename($absoluteImagePath);
                            // embed image into mail
                            $mailMessage->embed($response->getBody()->getContents(), $baseName, $response->getHeaderLine('Content-Type'));
                            // set image src to embed cid
                            $imageElement->setAttribute('src', 'cid:' . $baseName);
                        }
                    }
                }
                $mailMessage->html($html->saveHTML($domDocument));
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
            $files = MailerUtility::getAttachments($mail->getUid());
            /** @var FileReference $file */
            foreach ($files as $file) {
                $mailMessage->attach($file->getContents(), $file->getName(), $file->getMimeType());
            }
        }
    }
}
