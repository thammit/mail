<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class SysDmailTtAddressCategoryMmRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_ttaddress_category_mm';

    /**
     * @param int $uidLocal
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectUidsByUidLocal(int $uidLocal): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select(
                'uid_local',
                'uid_foreign'
            )
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($uidLocal, PDO::PARAM_INT)
                )
            )
            ->orderBy('sorting')
            ->execute()
            ->fetchAllAssociative();
    }
}
