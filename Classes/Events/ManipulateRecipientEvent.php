<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateRecipientEvent
{
    public function __construct(private array $recipientData, private readonly string $recipientTable) {
    }

    public function getRecipientData(): array
    {
        return $this->recipientData;
    }

    /**
     * @param array $recipientData
     * @return void
     */
    public function setRecipientData(array $recipientData): void
    {
        $this->recipientData = $recipientData;
    }

    /**
     * @return string
     */
    public function getRecipientTable(): string
    {
        return $this->recipientTable;
    }
}
