<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use TYPO3\CMS\Core\Database\Connection;
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
     */
    public function findResponseTypesByMail(int $mailUid): array
    {
        $responseTypes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)))
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::FAILED, Connection::PARAM_INT))
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, Connection::PARAM_INT))
            )
            ->groupBy('recipient_source')
            ->addGroupBy('recipient_uid')
            ->executeQuery()
            ->rowCount();
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, Connection::PARAM_INT)),
                $queryBuilder->expr()->like('recipient_source', $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($recipientSourceIdentifier) . '%')),
                $queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($recipientUid, Connection::PARAM_INT)),
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, Connection::PARAM_INT))
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(ResponseType::FAILED, Connection::PARAM_INT))
            );

        if ($returnCodes) {
            $or = [];
            foreach ($returnCodes as $returnCode) {
                $or[] = $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter($returnCode, Connection::PARAM_INT));
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
            $idLists[$row['recipient_source']][] = str_starts_with($row['recipient_source'], 'tx_mail_domain_model_group') ? $row['email'] : $row['recipient_uid'];
        }

        return $idLists;
    }

    /**
     * @param int $mailUid
     * @return void
     */
    public function deleteByMailUid(int $mailUid): void
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, Connection::PARAM_INT))
            )->executeStatement();
    }
}
