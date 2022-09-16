<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

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
}
