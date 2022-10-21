<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait RepositoryTrait
{
    /**
     * @param string $table
     * @return void
     */
    protected function setTable(string $table): void
    {
        $this->table = $table;
    }

    public function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string|null $table
     * @return Connection
     */
    public function getConnection(string $table = null): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table ?? $this->table);
    }

    /**
     * @param string|null $table
     * @return QueryBuilder
     */
    public function getQueryBuilder(string $table = null): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table ?? $this->table);
    }

    /**
     * @param bool $withDeleted
     * @return QueryBuilder
     */
    public function getQueryBuilderWithoutRestrictions(bool $withDeleted = false): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        if (!$withDeleted) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }
        return $queryBuilder;
    }

    /**
     * @param int $uid
     * @param array $fields
     * @param bool $withoutRestrictions
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findRecordByUid(int $uid, array $fields = ['*'], bool $withoutRestrictions = false): array
    {
        $queryBuilder = $withoutRestrictions ? $this->getQueryBuilderWithoutRestrictions() : $this->getQueryBuilder();

        return $queryBuilder
            ->select(...$fields)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pid
     * @param array $fields
     * @param bool $withoutRestrictions
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findRecordByPid(int $pid, array $fields = ['*'], bool $withoutRestrictions = false): array
    {
        $queryBuilder = $withoutRestrictions ? $this->getQueryBuilderWithoutRestrictions() : $this->getQueryBuilder();

        return $queryBuilder
            ->select(...$fields)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return int
     * @throws DBALException
     */
    public function deleteRecordByUid(int $uid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->executeStatement();
    }

    /**
     * @param int $pid
     * @return int
     * @throws DBALException
     */
    public function deleteRecordByPid(int $pid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->executeStatement();
    }
}
