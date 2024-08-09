<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use DateTimeImmutable;
use JsonException;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\File;

class Mail extends AbstractEntity
{
    protected int $type = MailType::INTERNAL;
    protected int $page = 0;
    protected int $step = 1;
    protected bool $sent = false;
    protected string $subject = '';
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected string $organisation = '';
    protected int $priority = 3;
    protected string $encoding = 'quoted-printable';
    protected string $charset = 'iso-8859-1';
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $attachment;
    protected SendFormat $sendOptions;
    protected bool $includeMedia = false;
    protected string $htmlParams = '';
    protected string $plainParams = '';
    protected int $renderedSize = 0;
    protected string $messageId = '';
    protected string $htmlContent = '';
    /**
     * @var string
     */
    protected string $previewImage = '';
    protected string $plainContent = '';
    /**
     * html links (do not delete this annotation block!)
     * @var string
     */
    protected string $htmlLinks = '[]';
    /**
     * plain links (do not delete this annotation block!)
     * @var string
     */
    protected string $plainLinks = '[]';
    protected ?DateTimeImmutable $scheduled = null;
    protected ?DateTimeImmutable $scheduledBegin = null;
    protected ?DateTimeImmutable $scheduledEnd = null;
    protected string $returnPath = '';
    protected bool $redirect = false;
    protected bool $redirectAll = false;
    protected string $redirectUrl = '';
    protected string $authCodeFields = '';
    /**
     * @var ObjectStorage<Group>
     */
    protected ObjectStorage $recipientGroups;
    /**
     * @var ObjectStorage<Group>
     */
    protected ObjectStorage $excludeRecipientGroups;
    /**
     * query info (do not delete this annotation block!)
     * @var string
     */
    protected string $recipients = '[]';
    /**
     * query info (do not delete this annotation block!)
     * @var string
     */
    protected string $recipientsHandled = '[]';
    protected int $numberOfRecipients = 0;
    protected int $numberOfRecipientsHandled = 0;
    protected int $deliveryProgress = 0;
    protected int $sysLanguageUid = 0;
    protected ?DateTimeImmutable $lastModified = null;

    public function __construct()
    {
        $this->attachment = new ObjectStorage();
        $this->recipientGroups = new ObjectStorage();
        $this->sendOptions = new SendFormat(SendFormat::NONE);
    }

    public function initializeObject(): void
    {
        $this->attachment = $this->attachment ?? new ObjectStorage();
        $this->recipientGroups = $this->recipientGroups ?? new ObjectStorage();
        $this->sendOptions = $this->sendOptions ?? new SendFormat(SendFormat::NONE);
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isInternal(): bool
    {
        return $this->type === MailType::INTERNAL;
    }

    /**
     * @return bool
     */
    public function isExternal(): bool
    {
        return $this->type === MailType::EXTERNAL;
    }

    public function isQuickMail(): bool
    {
        return $this->isExternal() && $this->getMessageId() && !$this->getHtmlParams() && !$this->getPlainParams();
    }

    /**
     * @param int $type
     * @return Mail
     */
    public function setType(int $type): Mail
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     * @return Mail
     */
    public function setPage(int $page): Mail
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return int
     */
    public function getStep(): int
    {
        return $this->step;
    }

    /**
     * @param int $step
     * @return Mail
     */
    public function setStep(int $step): Mail
    {
        $this->step = $step;
        return $this;
    }

    /**
     * @return ObjectStorage<File>
     */
    public function getAttachment(): ObjectStorage
    {
        return $this->attachment;
    }

    public function getAttachmentCsv(): string
    {
        $value = '';
        if ($this->attachment->count() > 0) {
            $attachments = [];
            foreach ($this->attachment as $attachment) {
                $attachments[] = $attachment->getOriginalResource()->getName();
            }
            $value = implode(', ', $attachments);
        }
        return $value;
    }

    /**
     * @param ObjectStorage<File> $attachment
     * @return Mail
     */
    public function setAttachment(ObjectStorage $attachment): Mail
    {
        $this->attachment = $attachment;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return Mail
     */
    public function setSubject(string $subject): Mail
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    /**
     * @param string $fromEmail
     * @return Mail
     */
    public function setFromEmail(string $fromEmail): Mail
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    /**
     * @return string
     */
    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * @param string $fromName
     * @return Mail
     */
    public function setFromName(string $fromName): Mail
    {
        $this->fromName = $fromName;
        return $this;
    }

    /**
     * @return string
     */
    public function getReplyToEmail(): string
    {
        return $this->replyToEmail;
    }

    /**
     * @param string $replyToEmail
     * @return Mail
     */
    public function setReplyToEmail(string $replyToEmail): Mail
    {
        $this->replyToEmail = $replyToEmail;
        return $this;
    }

    /**
     * @return string
     */
    public function getReplyToName(): string
    {
        return $this->replyToName;
    }

    /**
     * @param string $replyToName
     * @return Mail
     */
    public function setReplyToName(string $replyToName): Mail
    {
        $this->replyToName = $replyToName;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrganisation(): string
    {
        return $this->organisation;
    }

    /**
     * @param string $organisation
     * @return Mail
     */
    public function setOrganisation(string $organisation): Mail
    {
        $this->organisation = $organisation;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return Mail
     */
    public function setPriority(int $priority): Mail
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     * @return Mail
     */
    public function setEncoding(string $encoding): Mail
    {
        $this->encoding = $encoding;
        return $this;
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
     * @return Mail
     */
    public function setCharset(string $charset): Mail
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return SendFormat
     */
    public function getSendOptions(): SendFormat
    {
        return $this->sendOptions;
    }

    /**
     * @param SendFormat $sendOptions
     * @return Mail
     */
    public function setSendOptions(SendFormat $sendOptions): Mail
    {
        $this->sendOptions = $sendOptions;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPlain(): bool
    {
        return $this->sendOptions->get(SendFormat::PLAIN);
    }

    /**
     * @return bool
     */
    public function isHtml(): bool
    {
        return $this->sendOptions->get(SendFormat::HTML);
    }

    /**
     * @return bool
     */
    public function isPlainAndHtml(): bool
    {
        return $this->sendOptions->isBoth();
    }

    public function removeHtmlSendOption(): void
    {
        $this->sendOptions->unset(SendFormat::HTML);
    }

    public function removePlainSendOption(): void
    {
        $this->sendOptions->unset(SendFormat::PLAIN);
    }

    /**
     * @return bool
     */
    public function isIncludeMedia(): bool
    {
        return $this->includeMedia;
    }

    /**
     * @param bool $includeMedia
     * @return Mail
     */
    public function setIncludeMedia(bool $includeMedia): Mail
    {
        $this->includeMedia = $includeMedia;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlParams(): string
    {
        return $this->htmlParams;
    }

    /**
     * @param string $htmlParams
     * @return Mail
     */
    public function setHtmlParams(string $htmlParams): Mail
    {
        $this->htmlParams = $htmlParams;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlainParams(): string
    {
        return $this->plainParams;
    }

    /**
     * @param string $plainParams
     * @return Mail
     */
    public function setPlainParams(string $plainParams): Mail
    {
        $this->plainParams = $plainParams;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * @return int
     */
    public function getRenderedSize(): int
    {
        return $this->renderedSize;
    }

    public function recalculateRenderSize(): void
    {
        $this->renderedSize = strlen($this->htmlContent) + strlen($this->plainContent);
    }

    /**
     * @return string
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * @param string $messageId
     * @return Mail
     */
    public function setMessageId(string $messageId): Mail
    {
        $this->messageId = $messageId;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlContent(): string
    {
        return $this->htmlContent;
    }

    /**
     * @param string $htmlContent
     * @return Mail
     */
    public function setHtmlContent(string $htmlContent): Mail
    {
        $this->htmlContent = MailerUtility::removeDoubleBrTags($htmlContent);
        $this->recalculateRenderSize();
        return $this;
    }

    public function getMailBody(): string
    {
        if (str_contains($this->htmlContent, '<body')) {
            // html content contains html tag -> return body
            return MailerUtility::getMailBody($this->htmlContent);
        }
        return $this->htmlContent;
    }

    /**
     * @return string
     */
    public function getPreviewImage(): string
    {
        return $this->previewImage;
    }

    /**
     * @param string $previewImage
     * @return Mail
     */
    public function setPreviewImage(string $previewImage): Mail
    {
        $this->previewImage = $previewImage;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlainContent(): string
    {
        return $this->plainContent;
    }

    /**
     * @param string $plainContent
     * @return Mail
     */
    public function setPlainContent(string $plainContent): Mail
    {
        $this->plainContent = $plainContent;
        $this->recalculateRenderSize();
        return $this;
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function getHtmlLinks(): array
    {
        return json_decode($this->htmlLinks, true, 3, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
    }

    /**
     * @param array $htmlLinks
     * @return Mail
     * @throws JsonException
     */
    public function setHtmlLinks(array $htmlLinks): Mail
    {
        $this->htmlLinks = json_encode($htmlLinks, JSON_THROW_ON_ERROR, 3);
        return $this;
    }

    /**
     * @return array
     * @throws JsonException
     */
    public function getPlainLinks(): array
    {
        return json_decode($this->plainLinks, true, 3, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
    }

    /**
     * @param array $plainLinks
     * @return Mail
     * @throws JsonException
     */
    public function setPlainLinks(array $plainLinks): Mail
    {
        $this->plainLinks = json_encode($plainLinks, JSON_THROW_ON_ERROR, 3);
        return $this;
    }

    /**
     * @param string|null $identifier
     * @return array
     * @throws JsonException
     */
    public function getRecipients(string $identifier = null): array
    {
        $recipients = json_decode($this->recipients, true, 5, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        if ($identifier) {
            return $recipients[$identifier] ?? [];
        }
        return $recipients;
    }

    /**
     * @param array $recipients
     * @param bool $calculateNumberOfRecipients
     * @return Mail
     * @throws JsonException
     */
    public function setRecipients(array $recipients, bool $calculateNumberOfRecipients = false): Mail
    {
        $recipients = MailerUtility::removeDuplicateValues($recipients);
        $this->recipients = json_encode($recipients, JSON_THROW_ON_ERROR, 5);
        if ($calculateNumberOfRecipients) {
            $this->numberOfRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($recipients);
        }
        return $this;
    }

    /**
     * @param int $numberOfRecipients
     * @return Mail
     */
    public function setNumberOfRecipients(int $numberOfRecipients): Mail
    {
        $this->numberOfRecipients = $numberOfRecipients;
        return $this;
    }

    public function getNumberOfRecipients(): int
    {
        return $this->numberOfRecipients;
    }

    /**
     * @throws JsonException
     */
    public function getRecipientsHandled(string $identifier = null): array
    {
        $recipientsHandled = json_decode($this->recipientsHandled, true, 5,
            JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        if ($identifier) {
            return $recipientsHandled[$identifier] ?? [];
        }
        return $recipientsHandled;
    }

    /**
     * @throws JsonException
     */
    public function setRecipientsHandled(array $recipientsHandled): Mail
    {
        $recipientsHandled = MailerUtility::removeDuplicateValues($recipientsHandled);
        $this->recipientsHandled = json_encode($recipientsHandled, JSON_THROW_ON_ERROR, 5);
        $this->numberOfRecipientsHandled = RecipientUtility::calculateTotalRecipientsOfUidLists($recipientsHandled);
        $this->calculateDeliveryProgress();
        return $this;
    }

    public function getNumberOfRecipientsHandled(): int
    {
        return $this->numberOfRecipientsHandled;
    }

    /**
     * @throws JsonException
     */
    public function getRecipientsNotHandled(): array
    {
        $recipientsNotHandled = [];

        $recipients = $this->getRecipients();
        $recipientsHandled = $this->getRecipientsHandled();
        foreach ($recipients as $recipientIdentifier => $recipientUids) {
            if (isset($recipientsHandled[$recipientIdentifier])) {
                $recipientsNotHandled[$recipientIdentifier] = array_values(array_diff($recipientUids, $recipientsHandled[$recipientIdentifier]));
            } else {
                $recipientsNotHandled[$recipientIdentifier] = $recipientUids;
            }
            if (!$recipientsNotHandled[$recipientIdentifier]) {
                unset($recipientsNotHandled[$recipientIdentifier]);
            }
        }

        return $recipientsNotHandled;
    }

    /**
     * @throws JsonException
     */
    public function getNumberOfRecipientsNotHandled(): int
    {
        return RecipientUtility::calculateTotalRecipientsOfUidLists($this->getRecipientsNotHandled());
    }

    public function getDeliveryProgress(): int
    {
        return $this->deliveryProgress;
    }

    public function calculateDeliveryProgress(): void
    {
        if ($this->numberOfRecipients === 0 || $this->sent) {
            $this->deliveryProgress = 100;
        } else {
            $percentOfSent = $this->numberOfRecipientsHandled / $this->numberOfRecipients * 100;
            if ($percentOfSent > 100) {
                $percentOfSent = 100;
            }
            if ($percentOfSent < 0) {
                $percentOfSent = 0;
            }
            $this->deliveryProgress = (int)$percentOfSent;
        }
        $this->sent = $this->deliveryProgress === 100;
    }

    /**
     * @return ?DateTimeImmutable
     */
    public function getScheduled(): ?DateTimeImmutable
    {
        return $this->scheduled;
    }

    /**
     * @param DateTimeImmutable $scheduled
     * @return Mail
     */
    public function setScheduled(DateTimeImmutable $scheduled): Mail
    {
        $this->scheduled = $scheduled;
        return $this;
    }

    /**
     * @return ?DateTimeImmutable
     */
    public function getScheduledBegin(): ?DateTimeImmutable
    {
        return $this->scheduledBegin;
    }

    /**
     * @param DateTimeImmutable $scheduledBegin
     * @return Mail
     */
    public function setScheduledBegin(DateTimeImmutable $scheduledBegin): Mail
    {
        $this->scheduledBegin = $scheduledBegin;
        return $this;
    }

    /**
     * @return ?DateTimeImmutable
     */
    public function getScheduledEnd(): ?DateTimeImmutable
    {
        return $this->scheduledEnd;
    }

    /**
     * @param DateTimeImmutable $scheduledEnd
     * @return Mail
     */
    public function setScheduledEnd(DateTimeImmutable $scheduledEnd): Mail
    {
        $this->scheduledEnd = $scheduledEnd;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnPath(): string
    {
        return $this->returnPath;
    }

    /**
     * @param string $returnPath
     * @return Mail
     */
    public function setReturnPath(string $returnPath): Mail
    {
        $this->returnPath = $returnPath;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRedirect(): bool
    {
        return $this->redirect;
    }

    /**
     * @param bool $redirect
     * @return Mail
     */
    public function setRedirect(bool $redirect): Mail
    {
        $this->redirect = $redirect;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRedirectAll(): bool
    {
        return $this->redirectAll;
    }

    /**
     * @param bool $redirectAll
     * @return Mail
     */
    public function setRedirectAll(bool $redirectAll): Mail
    {
        $this->redirectAll = $redirectAll;
        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * @param string $redirectUrl
     * @return Mail
     */
    public function setRedirectUrl(string $redirectUrl): Mail
    {
        $this->redirectUrl = $redirectUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getAuthCodeFields(): string
    {
        return $this->authCodeFields;
    }

    /**
     * @param string $authCodeFields
     * @return Mail
     */
    public function setAuthCodeFields(string $authCodeFields): Mail
    {
        $this->authCodeFields = $authCodeFields;
        return $this;
    }

    /**
     * @return ObjectStorage
     */
    public function getRecipientGroups(): ObjectStorage
    {
        return $this->recipientGroups;
    }

    /**
     * @param ObjectStorage<Group> $recipientGroups
     * @return Mail
     */
    public function setRecipientGroups(ObjectStorage $recipientGroups): Mail
    {
        $this->recipientGroups = $recipientGroups;
        return $this;
    }

    public function getExcludeRecipientGroups(): ObjectStorage
    {
        return $this->excludeRecipientGroups;
    }

    public function setExcludeRecipientGroups(ObjectStorage $excludeRecipientGroups): Mail
    {
        $this->excludeRecipientGroups = $excludeRecipientGroups;
        return $this;
    }

    /**
     * @return int
     */
    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    /**
     * @param int $sysLanguageUid
     * @return Mail
     */
    public function setSysLanguageUid(int $sysLanguageUid): Mail
    {
        $this->sysLanguageUid = $sysLanguageUid;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getLastModified(): ?DateTimeImmutable
    {
        return $this->lastModified;
    }

    /**
     * @param DateTimeImmutable|null $lastModified
     * @return Mail
     */
    public function setLastModified(?DateTimeImmutable $lastModified): Mail
    {
        $this->lastModified = $lastModified;
        return $this;
    }

}
