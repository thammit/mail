<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Result;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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
