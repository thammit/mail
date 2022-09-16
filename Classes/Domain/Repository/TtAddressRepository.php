<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TtAddressRepository extends AbstractRepository
{
    protected string $table = 'tt_address';

    /**
     * @param int $uid
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectTtAddressByUid(int $uid, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table, $this->table)
            ->leftjoin(
                $this->table,
                'pages',
                'pages',
                $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier($this->table . '.pid'))
            )
            ->add('where', $this->table . '.uid = ' . $uid .
                ' AND ' . $permsClause . ' AND pages.deleted = 0')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pid
     * @param string $recordUnique
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectTtAddressByPid(int $pid, string $recordUnique): array
    {
        $queryBuilder = $this->getQueryBuilder();
        // only add deleteClause
        //https://github.com/FriendsOfTYPO3/tt_address/blob/master/Configuration/TCA/tt_address.php
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select(
                'uid',
                $recordUnique
            )
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
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
    public function selectTtAddressForTestmail(string $intList, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'pages',
                $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier($this->table . '.pid'))
            )
            ->add('where', $this->table . '.uid IN (' . $intList . ')' .
                ' AND ' . $permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $ttAddressUid
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectTtAddressForSendMailTest(int $ttAddressUid, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('a.*')
            ->from($this->table, 'a')
            ->leftJoin(
                'a',
                'pages',
                'pages',
                $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('a.pid'))
            )
            ->where(
                $queryBuilder->expr()->eq('a.uid', $queryBuilder->createNamedParameter($ttAddressUid, PDO::PARAM_INT))
            )
            ->andWhere($permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     */
    public function deleteRowsByPid(int $pid): int|\Doctrine\DBAL\Result
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                )
            )
            ->execute();
    }
}
