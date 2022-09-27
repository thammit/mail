<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class TtAddressRepository extends AbstractRepository
{
    protected string $table = 'tt_address';

    /**
     * @param int $uid
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByUidAndPermissionClause(int $uid, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier($this->table . '.pid'))
            )
            ->where(
                $queryBuilder->expr()->eq($this->table . '.uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->andWhere($permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pid
     * @param string $recordUnique
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByPid(int $pid, string $recordUnique): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder
            ->select(
                'uid',
                $recordUnique
            )
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param array $uids
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByUids(array $uids, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier($this->table . '.pid'))
            )
            ->where(
                $queryBuilder->expr()->in(
                    $this->table . '.uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->andWhere($permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     */
    public function deleteByPid(int $pid): int|\Doctrine\DBAL\Result
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                )
            )
            ->execute();
    }
}
