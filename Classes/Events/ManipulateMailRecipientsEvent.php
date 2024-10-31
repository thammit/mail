<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Mail;

final class ManipulateMailRecipientsEvent
{
    public function __construct(private Mail $mail, private array $recipients)
    {
    }

    public function getMail(): Mail
    {
        return $this->mail;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }
}
