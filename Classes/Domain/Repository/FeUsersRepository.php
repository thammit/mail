<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;

class FeUsersRepository extends AbstractRepository
{
    protected string $table = 'fe_users';

    /**
     * @param int $uid
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByUidAndPermissions(int $uid, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('f.*')
            ->from($this->table, 'f')
            ->leftjoin(
                $this->table,
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('f.pid'))
            )
            ->where(
                $queryBuilder->expr()->eq('f.uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->andWhere($permsClause)
            ->execute()
            ->fetchAllAssociative();
    }
}
