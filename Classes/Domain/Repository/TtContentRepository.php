<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use PDO;

class TtContentRepository
{
    use RepositoryTrait;
    protected string $table = 'tt_content';

    /**
     * @param int $pid
     * @param int $sysLanguageUid
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    public function findByPidAndSysLanguageUid(int $pid, int $sysLanguageUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('colPos', 'CType', 'list_type', 'uid', 'pid', 'header', 'bodytext', 'categories')
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
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
