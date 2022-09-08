<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class PagesRepository extends AbstractRepository
{
    protected string $table = 'pages';

    /**
     * @param int $pid
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectPagesForDmail(int $pid, string $permsClause): array
    {
        // Here the list of subpages, news, is rendered
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->select('uid', 'doktype', 'title', 'abstract')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq('l10n_parent', 0), // Exclude translated page records from list
                $permsClause
            );

        /**
         * https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ApiOverview/PageTypes/TypesOfPages.html
         * typo3/sysext/core/Classes/Domain/Repository/PageRepository.php
         *
         * Regards custom configurations, otherwise ignores spacers (199), recyclers (255) and folders (254)
         *
         **/

        return $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->notIn(
                    'doktype',
                    [199, 254, 255]
                )
            )
            ->orderBy('sorting')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pageUid
     * @param int $langUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectPageByL10nAndSysLanguageUid(int $pageUid, int $langUid): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

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
        $queryBuilder = $this->getQueryBuilder($this->table);

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
     * @return array|bool
     * @throws Exception
     * @throws DBALException
     */
    public function selectTitleTranslatedPage(int $pageUid, int $langUid): bool|array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('title')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, PDO::PARAM_INT)))
            ->execute()
            ->fetchAssociative();
    }

    /**
     * @param int $pageUid
     * @param string $tsConf
     * @return int
     */
    public function updatePageTSconfig(int $pageUid, string $tsConf): int
    {
        $connection = $this->getConnection($this->table);
        return $connection->update(
            $this->table,
            ['TSconfig' => $tsConf],
            ['uid' => $pageUid] // where
        );
    }
}
