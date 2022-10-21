<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use PDO;

class AddressRepository extends \FriendsOfTYPO3\TtAddress\Domain\Repository\AddressRepository
{
    use RepositoryTrait;
    protected string $table = 'tt_address';

    /**
     * @param int $pid
     * @return int
     * @throws DBALException
     */
    public function deleteByPid(int $pid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->executeStatement();
    }

}
