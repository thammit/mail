<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailGroupRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_group';

    /**
     * @param int $pid
     * @param string $defaultSortBy
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailGroupByPid(int $pid, string $defaultSortBy): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('uid', 'pid', 'title', 'description', 'type')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    $defaultSortBy
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pid
     * @param int $sysLanguageUid
     * @param string $defaultSortBy
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailGroupForFinalMail(int $pid, int $sysLanguageUid, string $defaultSortBy): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('uid', 'pid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    '-1, ' . $sysLanguageUid
                )
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    $defaultSortBy
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param string $intList
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailGroupForTestmail(string $intList, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'pages',
                $queryBuilder->expr()->eq($this->table . '.pid', $queryBuilder->quoteIdentifier('pages.uid'))
            )
            ->add('where', $this->table . '.uid IN (' . $intList . ')' .
                ' AND ' . $permsClause)
            ->execute()
            ->fetchAllAssociative();
    }
}
