<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateMarkersEvent
{
    public function __construct(
        private array $markers,
        private readonly array $recipient,
        private readonly string $recipientSourceIdentifier,
        private readonly array $recipientSourceConfiguration
    ) {
    }

    /**
     * @return array
     */
    public function getMarkers(): array
    {
        return $this->markers;
    }

    /**
     * @param array $markers
     */
    public function setMarkers(array $markers): void
    {
        $this->markers = $markers;
    }

    public function getRecipient(): array
    {
        return $this->recipient;
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
