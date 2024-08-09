<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;

final class ManipulateRecipientEvent
{
    public function __construct(private array $recipientData, private RecipientSourceConfigurationDTO $recipientSourceConfiguration) {
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

    public function getRecipientSourceIdentifier(): string
    {
        return $this->recipientSourceConfiguration->identifier;
    }

    public function getRecipientSourceConfiguration(): RecipientSourceConfigurationDTO
    {
        return $this->recipientSourceConfiguration;
    }
}
