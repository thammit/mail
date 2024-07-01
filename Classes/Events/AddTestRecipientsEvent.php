<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class AddTestRecipientsEvent
{
    protected array $receiver = [];

    public function __construct(private string $recipientGroup)
    {
    }

    public function getRecipientGroup(): string
    {
        return $this->recipientGroup;
    }

    public function addRecipients(array $recipients): void
    {
        $this->receiver = array_merge($this->receiver, $recipients);
    }

    public function getRecipients(): array
    {
        return $this->receiver;
    }
}
