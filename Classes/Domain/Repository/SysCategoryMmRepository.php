<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;

class SysCategoryMmRepository
{
    use RepositoryTrait;
    protected string $table = 'sys_category_record_mm';

    /**
     * @param int $uidForeign
     * @param string $tableNames
     * @param string $fieldName
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByUidForeignTableNameFieldName(int $uidForeign, string $tableNames, string $fieldName = 'categories'): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid_local')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $uidForeign),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($tableNames)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($fieldName))
            )
            ->execute()
            ->fetchAllAssociative();
    }
}
