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
    public function selectFeUsersByUid(int $uid, string $permsClause): array
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
            ->add('where', $this->table . '.uid = ' . intval($uid) .
                ' AND ' . $permsClause . ' AND pages.deleted = 0')

            ->execute()
            ->fetchAllAssociative();
    }
}
