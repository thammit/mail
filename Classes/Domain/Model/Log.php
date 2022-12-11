<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use DateTimeImmutable;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Log extends AbstractEntity
{
    /**
     * @var Mail
     */
    protected Mail $mail;

    /**
     * @var string
     */
    protected string $recipientSource = '';

    /**
     * @var int
     */
    protected int $recipientUid;

    /**
     * @var string
     */
    protected string $email = '';

    /**
     * @var string
     */
    protected string $url = '';

    /**
     * @var int
     */
    protected int $parseTime = 0;

    /**
     * @var int
     */
    protected int $responseType = 0;

    /**
     * @var SendFormat
     */
    protected SendFormat $formatSent;

    /**
     * @var int
     */
    protected int $urlId = 0;

    /**
     * @var string
     */
    protected string $returnContent = '';

    /**
     * @var int
     */
    protected int $returnCode = 0;

    /**
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $lastChange;

    public function __construct()
    {
        $this->formatSent = new SendFormat(SendFormat::NONE);
    }
    public function initializeObject(): void
    {
        $this->formatSent = $this->formatSent ?? new SendFormat(SendFormat::NONE);
    }

    /**
     * @return Mail
     */
    public function getMail(): Mail
    {
        return $this->mail;
    }

    /**
     * @param Mail $mail
     * @return Log
     */
    public function setMail(Mail $mail): Log
    {
        $this->mail = $mail;
        return $this;
    }

    /**
     * @return string
     */
    public function getRecipientSource(): string
    {
        return $this->recipientSource;
    }

    /**
     * @param string $recipientSource
     * @return Log
     */
    public function setRecipientSource(string $recipientSource): Log
    {
        $this->recipientSource = $recipientSource;
        return $this;
    }

    /**
     * @return int
     */
    public function getRecipientUid(): int
    {
        return $this->recipientUid;
    }

    /**
     * @param int $recipientUid
     * @return Log
     */
    public function setRecipientUid(int $recipientUid): Log
    {
        $this->recipientUid = $recipientUid;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return Log
     */
    public function setEmail(string $email): Log
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return Log
     */
    public function setUrl(string $url): Log
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return int
     */
    public function getParseTime(): int
    {
        return $this->parseTime;
    }

    /**
     * @param int $parseTime
     * @return Log
     */
    public function setParseTime(int $parseTime): Log
    {
        $this->parseTime = $parseTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getResponseType(): int
    {
        return $this->responseType;
    }

    /**
     * @param int $responseType
     * @return Log
     */
    public function setResponseType(int $responseType): Log
    {
        $this->responseType = $responseType;
        return $this;
    }

    /**
     * @return SendFormat
     */
    public function getFormatSent(): SendFormat
    {
        return $this->formatSent;
    }

    /**
     * @param SendFormat $formatSent
     * @return Log
     */
    public function setFormatSent(SendFormat $formatSent): Log
    {
        $this->formatSent = $formatSent;
        return $this;
    }

    /**
     * @return int
     */
    public function getUrlId(): int
    {
        return $this->urlId;
    }

    /**
     * @param int $urlId
     * @return Log
     */
    public function setUrlId(int $urlId): Log
    {
        $this->urlId = $urlId;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnContent(): string
    {
        return $this->returnContent;
    }

    /**
     * @param string $returnContent
     * @return Log
     */
    public function setReturnContent(string $returnContent): Log
    {
        $this->returnContent = $returnContent;
        return $this;
    }

    /**
     * @return int
     */
    public function getReturnCode(): int
    {
        return $this->returnCode;
    }

    /**
     * @param int $returnCode
     * @return Log
     */
    public function setReturnCode(int $returnCode): Log
    {
        $this->returnCode = $returnCode;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getLastChange(): ?DateTimeImmutable
    {
        return $this->lastChange;
    }

    /**
     * @param DateTimeImmutable|null $lastChange
     * @return Log
     */
    public function setLastChange(?DateTimeImmutable $lastChange): Log
    {
        $this->lastChange = $lastChange;
        return $this;
    }

}
