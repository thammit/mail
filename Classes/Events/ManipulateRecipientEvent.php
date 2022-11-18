<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateRecipientEvent
{
    public function __construct(private array $recipient) {
    }

    public function getRecipient(): array
    {
        return $this->recipient;
    }

    /**
     * @param array $recipient
     * @return void
     */
    public function setRecipient(array $recipient): void
    {
        $this->recipient = $recipient;
    }
}
