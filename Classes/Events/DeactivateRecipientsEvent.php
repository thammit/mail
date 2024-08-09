<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;

final class DeactivateRecipientsEvent
{
    private int $numberOfAffectedRecipients = 0;

    public function __construct(
        private array $data,
        private array $recipientSources,
    )
    {}

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return RecipientSourceConfigurationDTO[]
     */
    public function getRecipientSources(): array
    {
        return $this->recipientSources;
    }

    public function setNumberOfAffectedRecipients(int $numberOfAffectedRecipients): void
    {
        $this->numberOfAffectedRecipients = $numberOfAffectedRecipients;
    }

    public function getNumberOfAffectedRecipients(): int
    {
        return $this->numberOfAffectedRecipients;
    }
}
