<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class PagesRepository
{
    use RepositoryTrait;
    protected string $table = 'pages';

    /**
     * @param int $pageUid
     * @param int $langUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectPageByL10nAndSysLanguageUid(int $pageUid, int $langUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('sys_language_uid')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, PDO::PARAM_INT)))
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSubfolders(string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid', 'title')
            ->from($this->table)
            ->where(
                $permsClause,
                $queryBuilder->expr()->eq(
                    'doktype',
                    '254'
                )
            )
            ->orderBy('uid')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pageUid
     * @param string $tsConf
     * @return int
     */
    public function updatePageTSconfig(int $pageUid, string $tsConf): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table,
            ['TSconfig' => $tsConf],
            ['uid' => $pageUid]
        );
    }
}
