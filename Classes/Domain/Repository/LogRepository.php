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
    public function persist(): void
    {
        $this->persistenceManager->persistAll();
    }

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
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findResponseTypesByMail(int $mailUid): array
    {
        $responseTypes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->groupBy('response_type')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $responseTypes[$row['response_type']] = $row['COUNT(*)'];
        }

        return $responseTypes;
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findReturnCodesByMail(int $mailUid): array
    {
        $returnCodes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('return_code')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::FAILED, PDO::PARAM_INT))
            )
            ->groupBy('return_code')
            ->orderBy('COUNT(*)')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $returnCodes[$row['return_code']] = $row['COUNT(*)'];
        }

        return $returnCodes;
    }

    /**
     * @param int $mailUid
     * @param int $responseType
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countByMailAndResponseType(int $mailUid, int $responseType): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return (int)$queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $responseType)
            )
            ->groupBy('recipient_uid')
            ->addGroupBy('recipient_table')
            ->orderBy('COUNT(*)')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findFormatSentByMail(int $mailUid): array
    {
        $formatSent = [];

        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('format_sent')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', ResponseType::ALL),
            )
            ->groupBy('format_sent')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $formatSent[$row['format_sent']] = $row['COUNT(*)'];
        }

        return $formatSent;
    }

    /**
     *
     * @param int $mailUid
     * @param int $responseType 1 for html, 2 for plain
     * @return array
     * @throws Exception
     * @throws DBALException
     */
    public function findMostPopularLinksByMailAndResponseType(int $mailUid, int $responseType = ResponseType::HTML): array
    {
        $popularLinks = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('url_id')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, PDO::PARAM_INT))
            )
            ->groupBy('url_id')
            ->orderBy('COUNT(*)')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $popularLinks[$row['url_id']] = $row['COUNT(*)'];
        }

        return $popularLinks;
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
            'addresses' => [],
            'frontendUsers' => [],
            'plainList' => [],
        ];

        foreach ($result as $row) {
            switch ($row['recipient_table']) {
                case 't':
                    $idLists['addresses'][] = $row['recipient_uid'];
                    break;
                case 'f':
                    $idLists['frontendUsers'][] = $row['recipient_uid'];
                    break;
                case 'P':
                    $idLists['plainList'][] = $row['email'];
                    break;
                default:
                    $idLists[$row['recipient_table']][] = $row['recipient_uid'];
            }
        }

        $returnedList = [];

        if (count($idLists['addresses'])) {
            $addressRepository = GeneralUtility::makeInstance(AddressRepository::class);
            $demand = new Demand();
            $demand->setSingleRecords(implode(',', $idLists['addresses']));
            $returnedList['addresses'] = $addressRepository->getAddressesByCustomSorting($demand);
        }
        if (count($idLists['frontendUsers'])) {
            $frontendUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
            $frontendUsers = $frontendUserRepository->findByUidList($idLists['frontendUsers']);
            $returnedList['frontendUsers'] = $frontendUsers;
        }
        if (count($idLists['plainList'])) {
            $returnedList['plainList'] = $idLists['plainList'];
        }

        return $returnedList;
    }
}
