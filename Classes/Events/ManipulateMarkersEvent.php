<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;

final class ManipulateMarkersEvent
{
    public function __construct(
        private array $markers,
        private array $recipient,
        private RecipientSourceConfigurationDTO $recipientSourceConfiguration
    ) {
    }

    public function getMarkers(): array
    {
        return $this->markers;
    }

    public function setMarkers(array $markers): void
    {
        $this->markers = $markers;
    }

    public function getRecipient(): array
    {
        return $this->recipient;
    }

    public function getRecipientSourceConfiguration(): RecipientSourceConfigurationDTO
    {
        return $this->recipientSourceConfiguration;
    }

}
