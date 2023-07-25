<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Mail;
use Symfony\Component\Mime\Header\Headers;

final class AdditionalMailHeadersEvent
{
    public function __construct(private Headers $headers, private Mail $mail, private string $typo3MailId, private string $organisation, private string $siteIdentifier)
    {
    }

    /**
     * @return Headers
     */
    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getMail(): Mail
    {
        return $this->mail;
    }

    /**
     * @return string
     */
    public function getTypo3MailId(): string
    {
        return $this->typo3MailId;
    }

    /**
     * @return string
     */
    public function getOrganisation(): string
    {
        return $this->organisation;
    }

    /**
     * @return string
     */
    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }
}
