<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Enumeration\MailType;

class SysDmailRepository extends AbstractRepository
{
    protected string $table = 'tx_mail_domain_model_mail';

    /**
     * @return array|bool
     * @throws DBALException
     * @throws Exception
     */
    public function findMailsToSend(): array|bool
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq('scheduled', 0),
                $queryBuilder->expr()->lt('scheduled', time()),
                $queryBuilder->expr()->eq('scheduled_end', 0),
                $queryBuilder->expr()->notIn('type', [MailType::DRAFT_INTERNAL, MailType::DRAFT_EXTERNAL])
            )
            ->orderBy('scheduled')
            ->execute()
            ->fetchAssociative();
    }
}
