<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateMarkersEvent
{
    public function __construct(private array $markers, private array $recipient) {
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

}
