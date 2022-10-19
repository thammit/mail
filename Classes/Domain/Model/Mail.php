<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use DateTimeImmutable;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\File;

class Mail extends AbstractEntity
{
    protected int $type = MailType::INTERNAL;
    protected int $page = 0;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $attachment;
    protected string $subject = '';
    protected string $fromEmail = '';
    protected string $fromName = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected string $organisation = '';
    protected int $priority = 3;
    protected string $encoding = 'quoted-printable';
    protected string $charset = 'iso-8859-1';
    protected int $sendOptions = 0;
    protected bool $includeMedia = false;
    protected bool $flowedFormat = false;
    protected string $htmlParams = '';
    protected string $plainParams = '';
    protected bool $sent = false;
    protected int $recipients = 0;
    protected int $renderedSize = 0;
    protected string $mailContent = '';
    protected string $queryInfo = '';
    protected ?DateTimeImmutable $scheduled = null;
    protected ?DateTimeImmutable $scheduledBegin = null;
    protected ?DateTimeImmutable $scheduledEnd = null;
    protected string $returnPath = '';
    protected bool $redirect = false;
    protected bool $redirectAll = false;
    protected string $redirectUrl = '';
    protected string $authCodeFields = '';
    protected string $recipientGroups = '';
    protected int $sysLanguageUid = 0;
    protected ?DateTimeImmutable $lastModified = null;

    public function __construct()
    {
        $this->attachment = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->attachment = $this->attachment ?? new ObjectStorage();
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
     * @return ObjectStorage<File>
     */
    public function getAttachment(): ObjectStorage
    {
        return $this->attachment;
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
     * @return int
     */
    public function getSendOptions(): int
    {
        return $this->sendOptions;
    }

    /**
     * @return bool
     */
    public function isHtml(): bool
    {
        return ($this->sendOptions & 2) !== 0;
    }

    /**
     * @return bool
     */
    public function isPlain(): bool
    {
        return ($this->sendOptions & 1) !== 0;
    }

    /**
     * @return bool
     */
    public function isPlainAndHtml(): bool
    {
        return ($this->sendOptions & 3) !== 0;
    }

    /**
     * @param int $sendOptions
     * @return Mail
     */
    public function setSendOptions(int $sendOptions): Mail
    {
        $this->sendOptions = $sendOptions;
        return $this;
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
     * @return bool
     */
    public function isFlowedFormat(): bool
    {
        return $this->flowedFormat;
    }

    /**
     * @param bool $flowedFormat
     * @return Mail
     */
    public function setFlowedFormat(bool $flowedFormat): Mail
    {
        $this->flowedFormat = $flowedFormat;
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
     * @param bool $sent
     * @return Mail
     */
    public function setSent(bool $sent): Mail
    {
        $this->sent = $sent;
        return $this;
    }

    /**
     * @return int
     */
    public function getRecipients(): int
    {
        return $this->recipients;
    }

    /**
     * @param int $recipients
     * @return Mail
     */
    public function setRecipients(int $recipients): Mail
    {
        $this->recipients = $recipients;
        return $this;
    }

    /**
     * @return int
     */
    public function getRenderedSize(): int
    {
        return $this->renderedSize;
    }

    /**
     * @param int $renderedSize
     * @return Mail
     */
    public function setRenderedSize(int $renderedSize): Mail
    {
        $this->renderedSize = $renderedSize;
        return $this;
    }

    /**
     * @return string
     */
    public function getMailContent(): string
    {
        return $this->mailContent;
    }

    /**
     * @param string $mailContent
     * @return Mail
     */
    public function setMailContent(string $mailContent): Mail
    {
        $this->mailContent = $mailContent;
        return $this;
    }

    /**
     * @return string
     */
    public function getQueryInfo(): string
    {
        return $this->queryInfo;
    }

    /**
     * @param string $queryInfo
     * @return Mail
     */
    public function setQueryInfo(string $queryInfo): Mail
    {
        $this->queryInfo = $queryInfo;
        return $this;
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
     * @return string
     */
    public function getRecipientGroups(): string
    {
        return $this->recipientGroups;
    }

    /**
     * @param string $recipientGroups
     * @return Mail
     */
    public function setRecipientGroups(string $recipientGroups): Mail
    {
        $this->recipientGroups = $recipientGroups;
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
