<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractRepository
{
    protected string $table = '';

    public function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    public function getConnection(string $table = null): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table ?? $this->table);
    }

    public function getQueryBuilder(string $table = null): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table ?? $this->table);
    }

    public function findByUid(int $uid, string $fields = '*', string $where = '', bool $useDeleteClause = true): ?array
    {
        return BackendUtility::getRecord($this->table, $uid, $fields, $where, $useDeleteClause);
    }
}
