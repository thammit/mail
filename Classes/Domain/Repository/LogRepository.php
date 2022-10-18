<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use FriendsOfTYPO3\TtAddress\Domain\Model\Dto\Demand;
use MEDIAESSENZ\Mail\Enumeration\ResponseType;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Repository;

class LogRepository extends Repository
{
    private string $table = 'tx_mail_domain_model_log';

    public function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
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
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT))
            )
            ->orderBy('recipient_uid', 'ASC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @param array $returnCodes
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable(int $mailUid, array $returnCodes = []): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::FAILED, PDO::PARAM_INT))
            );

        if ($returnCodes) {
            $or = [];
            foreach ($returnCodes as $returnCode) {
                $or[] = $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter($returnCode, PDO::PARAM_INT));
            }
            if (count($or) > 1) {
                $statement->andWhere(
                    $queryBuilder->expr()->or(...$or)
                );
            } else {
                $statement->andWhere($or[0]);
            }
        }

        $result = $statement
            ->execute()
            ->fetchAllAssociative();

        $idLists = [
            'tt_address' => [],
            'fe_users' => [],
            'plainList' => [],
        ];

        foreach ($result as $row) {
            switch ($row['recipient_table']) {
                case 't':
                    $idLists['tt_address'][] = $row['recipient_uid'];
                    break;
                case 'f':
                    $idLists['fe_users'][] = $row['recipient_uid'];
                    break;
                case 'P':
                    $idLists['plainList'][] = $row['email'];
                    break;
                default:
                    $idLists[$row['recipient_table']][] = $row['recipient_uid'];
            }
        }

        $returnedList = [];

        if (count($idLists['tt_address'])) {
            $addressRepository = GeneralUtility::makeInstance(AddressRepository::class);
            $demand = new Demand();
            $demand->setSingleRecords(implode(',', $idLists['tt_address']));
            $returnedList['addresses'] = $addressRepository->getAddressesByCustomSorting($demand);
        }
        if (count($idLists['fe_users'])) {
            $tempRepository = GeneralUtility::makeInstance(TempRepository::class);
            $frontendUsers = $tempRepository->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
            $returnedList['frontendUsers'] = $frontendUsers;
        }
        if (count($idLists['plainList'])) {
            $returnedList['plainList'] = $idLists['plainList'];
        }

        return $returnedList;
    }
}
