<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

final class RecipientsRestrictionEvent
{
    public function __construct(private RecipientSourceConfigurationDTO $recipientSourceConfiguration, private QueryInterface $query, private array $constraints) {
    }

    public function getRecipientSourceConfiguration(): RecipientSourceConfigurationDTO
    {
        return $this->recipientSourceConfiguration;
    }

    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function setConstraints(array $constraints): void
    {
        $this->constraints = $constraints;
    }
}
