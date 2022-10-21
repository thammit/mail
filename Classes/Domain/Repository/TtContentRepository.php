<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class TtContentRepository
{
    use RepositoryTrait;
    protected string $table = 'tt_content';

    /**
     * @param int $pid
     * @param int $sysLanguageUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByPidAndSysLanguageUid(int $pid, int $sysLanguageUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('colPos', 'CType', 'list_type', 'uid', 'pid', 'header', 'bodytext', 'module_sys_dmail_category', 'categories')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($sysLanguageUid, PDO::PARAM_INT)
                )
            )
            ->orderBy('colPos')
            ->addOrderBy('sorting')
            ->execute()
            ->fetchAllAssociative();
    }
}
