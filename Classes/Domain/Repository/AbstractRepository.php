<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use MEDIAESSENZ\Mail\Utility\TcaUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
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

    public function getQueryBuilderWithoutRestrictions($withDeleted = false, string $table = null): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        if (!$withDeleted) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }
        return $queryBuilder;
    }

    public function getDefaultOrderBy(): string
    {
        $orderBy = TcaUtility::getDefaultSortByFromTca($this->table);
        if (!empty($orderBy)) {
            // remove ASC/DESC from $orderBy
            if (str_contains($orderBy, 'ASC')) {
                $orderBy = trim(str_replace('ASC', '', $orderBy));
            } else if (str_contains($orderBy, 'DESC')) {
                $orderBy = trim(str_replace('DESC', '', $orderBy));
                $order = 'DESC';
            }
        }

        return $orderBy;
    }

    public function getDefaultOrder(): string
    {
        $orderBy = TcaUtility::getDefaultSortByFromTca($this->table);
        $order = 'ASC';
        if (!empty($orderBy)) {
            // remove ASC/DESC from $orderBy
            if (str_contains('ASC', $orderBy)) {
            } else if (str_contains('DESC', $orderBy)) {
                $order = 'DESC';
            }
        }

        return $order;
    }

    public function findByUid(int $uid, string $fields = '*', string $where = '', bool $useDeleteClause = true): ?array
    {
        return BackendUtility::getRecord($this->table, $uid, $fields, $where, $useDeleteClause);
    }
}
