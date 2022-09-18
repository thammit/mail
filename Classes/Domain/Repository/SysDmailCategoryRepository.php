<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class SysDmailCategoryRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_category';

    /**
     * @param int $pid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailCategoryByPid(int $pid): array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    public function update(): array
    {

    }
}
