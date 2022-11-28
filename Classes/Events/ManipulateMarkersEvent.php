<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

final class ManipulateMarkersEvent
{
    public function __construct(private array $markers, private readonly array $recipient, private readonly string $tableName) {
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
    public function getTableName(): string
    {
        return $this->tableName;
    }

}
