<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Enumeration\ResponseType;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class LogRepository extends Repository
{
    public function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mail_domain_model_log');
    }

    public function getQueryBuilderWithoutRestrictions($withDeleted = false): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        if (!$withDeleted) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }
        return $queryBuilder;
    }

    /**
     * @param int $mailUid
     * @return array[]
     * @throws DBALException
     * @throws Exception
     */
    public function findAllByMailUid(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid')
            ->from('tx_mail_domain_model_log')
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT))
            )
            ->orderBy('recipient_uid', 'ASC')
            ->execute()
            ->fetchAllAssociative();
    }
}
