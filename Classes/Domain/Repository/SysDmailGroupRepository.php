<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class SysDmailGroupRepository extends AbstractRepository
{
    protected string $table = 'tx_mail_domain_model_group';
//
//    /**
//     * @param array $uids
//     * @param string $permsClause
//     * @return array
//     * @throws DBALException
//     * @throws Exception
//     */
//    public function findByUids(array $uids, string $permsClause): array
//    {
//        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();
//
//        return $queryBuilder
//            ->select($this->table . '.*')
//            ->from($this->table)
//            ->leftJoin(
//                $this->table,
//                'pages',
//                'p',
//                $queryBuilder->expr()->eq($this->table . '.pid', $queryBuilder->quoteIdentifier('p.uid'))
//            )
//            ->where(
//                $queryBuilder->expr()->in(
//                    $this->table . '.uid',
//                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
//                )
//            )
//            ->andWhere($permsClause)
//            ->execute()
//            ->fetchAllAssociative();
//    }

    /**
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function update(int $uid, array $updateData): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table,
            $updateData,
            ['uid' => $uid]
        );
    }

}
