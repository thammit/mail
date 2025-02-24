<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\Connection;

class PagesRepository
{
    use RepositoryTrait;
    protected string $table = 'pages';

    /**
     * @param string $permsClause
     * @return array
     * @throws \Doctrine\DBAL\Exception
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
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    public function findMailModulePageUids(): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return array_column($queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('module', $queryBuilder->createNamedParameter('mail')))
            ->executeQuery()
            ->fetchAllAssociative(), 'uid');
    }

    /**
     * @param int $pageUid
     * @param string $tsConf
     * @return int
     */
    public function updatePageTsConfig(int $pageUid, string $tsConf): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table,
            ['TSconfig' => $tsConf],
            ['uid' => $pageUid]
        );
    }
}
