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

    /**
     *
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function update(int $uid, array $updateData): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table, // table
            $updateData, // value array
            ['uid' => $uid]
        );
    }

    /**
     * @param int $uid
     * @return void
     */
    public function delete(int $uid): void
    {
        if ($GLOBALS['TCA'][$this->table]['ctrl']['delete']) {
            $this->update($uid, [$GLOBALS['TCA'][$this->table]['ctrl']['delete'] => 1]);
        }
    }
}
