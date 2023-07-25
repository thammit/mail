<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Mail;

final class ScheduledSendBegunEvent
{
    public function __construct(private Mail $mail)
    {
    }

    public function getMail(): Mail
    {
        return $this->mail;
    }

    public function setMail(Mail $mail): void
    {
        $this->mail = $mail;
    }
}
