<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use PDO;
use TYPO3\CMS\Extbase\Persistence\Repository;

class LogRepository extends Repository
{
    use RepositoryTrait;
    protected string $table = 'tx_mail_domain_model_log';

    public function persist(): void
    {
        $this->persistenceManager->persistAll();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws Exception
     * @throws DBALException|\Doctrine\DBAL\Driver\Exception
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
            ->executeQuery();

        while ($row = $statement->fetchAssociative()) {
            $responseTypes[$row['response_type']] = $row['COUNT(*)'];
        }

        return $responseTypes;
    }

    /**
     * @param int $mailUid
     * @return array
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
            ->executeQuery();

        while ($row = $statement->fetchAssociative()) {
            $returnCodes[$row['return_code']] = $row['COUNT(*)'];
        }

        return $returnCodes;
    }

    /**
     * @param int $mailUid
     * @param int $responseType
     * @return int
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
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, PDO::PARAM_INT))
            )
            ->groupBy('recipient_source')
            ->addGroupBy('recipient_uid')
            ->executeQuery()
            ->rowCount();
    }

    /**
     * @param int $mailUid
     * @return int
     * @throws Exception
     * @deprecated
     */
    public function countByMailUid(int $mailUid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('format_sent', $queryBuilder->createNamedParameter(SendFormat::NONE, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Get array of already sent recipient ids
     *
     * @param int $mailUid uid of the mail record
     * @param string $recipientSourceIdentifier Recipient source identifier
     *
     * @return array list of recipients
     * @throws Exception
     * @deprecated
     */
    public function findRecipientsByMailUidAndRecipientSourceIdentifier(int $mailUid, string $recipientSourceIdentifier): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return array_column($queryBuilder
            ->select('recipient_uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_source', $queryBuilder->createNamedParameter($recipientSourceIdentifier)),
            )
            ->executeQuery()
            ->fetchAllAssociative(), 'recipient_uid');
    }

    /**
     * @param int $mailUid
     * @return array
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
            ->executeQuery();

        while ($row = $statement->fetchAssociative()) {
            $formatSent[$row['format_sent']] = $row['COUNT(*)'];
        }

        return $formatSent;
    }

    /**
     * @param int $recipientUid
     * @param string $recipientSourceIdentifier
     * @param int $mailUid
     * @return bool|array
     * @throws Exception
     */
    public function findOneByRecipientUidAndRecipientSourceIdentifierAndMailUid(int $recipientUid, string $recipientSourceIdentifier, int $mailUid): bool|array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_source', $queryBuilder->createNamedParameter($recipientSourceIdentifier)),
                $queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($recipientUid, PDO::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     *
     * @param int $mailUid
     * @param int $responseType 1 for html, 2 for plain
     * @return array
     * @throws Exception
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
            ->executeQuery();

        while ($row = $statement->fetchAssociative()) {
            $popularLinks[$row['url_id']] = $row['COUNT(*)'];
        }

        return $popularLinks;
    }

    /**
     * @param int $mailUid
     * @param array $returnCodes
     * @return array
     * @throws Exception
     */
    public function findFailedRecipientIdsByMailAndReturnCodeGroupedByRecipientSource(int $mailUid, array $returnCodes = []): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->select('recipient_uid', 'recipient_source', 'email')
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

        $result = $statement->executeQuery();
        $idLists = [];

        while ($row = $result->fetchAssociative()) {
            $idLists[$row['recipient_source']][] = $row['recipient_source'] === 'tx_mail_domain_model_group' ? $row['email'] : $row['recipient_uid'];
        }

        return $idLists;
    }

    /**
     * @param int $mailUid
     * @return void
     */
    public function deleteByMailUid(int $mailUid) {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT))
            )->executeStatement();
    }
}
