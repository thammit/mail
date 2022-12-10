<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateRecipientEvent
{
    public function __construct(private array $recipientData, private readonly string $recipientSourceIdentifier, private readonly array $recipientSourceConfiguration) {
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
    public function getRecipientSourceIdentifier(): string
    {
        return $this->recipientSourceIdentifier;
    }

    /**
     * @return array
     */
    public function getRecipientSourceConfiguration(): array
    {
        return $this->recipientSourceConfiguration;
    }
}
