<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use PDO;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class MailRepository extends Repository
{
    use RepositoryTrait;
    protected string $table = 'tx_mail_domain_model_mail';

    public function initializeObject()
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    public function persist(): void
    {
        $this->persistenceManager->persistAll();
    }

    /**
     * @param int $pid
     * @return object[]|QueryResultInterface
     */
    public function findOpenByPid(int $pid): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd([
                $query->equals('scheduled', 0),
                $query->equals('sent', 0),
                $query->equals('pid', $pid),
            ])
        );
        return $query->execute();
    }

    /**
     * @param int $pid
     * @param int $page
     * @return object[]|QueryResultInterface
     */
    public function findOpenByPidAndPage(int $pid, int $page): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd([
                $query->equals('scheduled', 0),
                $query->equals('sent', 0),
                $query->equals('page', $page),
                $query->equals('pid', $pid),
            ])
        );
        return $query->execute();
    }

    /**
     * @throws InvalidQueryException
     */
    public function findScheduledByPid(int $pid): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd([
                $query->equals('pid', $pid),
                $query->greaterThan('scheduled', 0),
            ])
        );
        $query->setOrderings(['scheduled' => QueryInterface::ORDER_DESCENDING]);
        return $query->execute();
    }

    /**
     * @throws InvalidQueryException
     */
    public function findMailToSend(): ?Mail
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->matching(
            $query->logicalAnd([
                $query->logicalNot($query->equals('scheduled', 0)),
                $query->lessThan('scheduled', new DateTimeImmutable('now')),
                $query->equals('scheduledEnd', 0),
                $query->logicalNot($query->in('type', [MailType::DRAFT_INTERNAL, MailType::DRAFT_EXTERNAL]))
            ])
        );
        $query->setOrderings(['scheduled' => QueryInterface::ORDER_ASCENDING]);

        return $query->execute()->getFirst();
    }

    /**
     * @param int $pid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findSentByPid(int $pid): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return GeneralUtility::makeInstance(DataMapper::class)->map(Mail::class, $queryBuilder
            ->selectLiteral(
            'm.uid',
            'm.subject',
            'm.scheduled',
            'm.scheduled_begin',
            'm.scheduled_end',
            'm.recipients',
            'COUNT(l.mail) AS number_of_sent'
            )
            ->from('tx_mail_domain_model_mail', 'm')
            ->leftJoin(
                'm',
                'tx_mail_domain_model_log',
                'l',
                $queryBuilder->expr()->eq('m.uid', $queryBuilder->quoteIdentifier('l.mail'))
            )
            ->where(
                $queryBuilder->expr()->eq('m.pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)),
                $queryBuilder->expr()->in('m.type', $queryBuilder->createNamedParameter([MailType::INTERNAL, MailType::EXTERNAL], Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('m.sent', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('l.response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, PDO::PARAM_INT)),
                $queryBuilder->expr()->neq('l.format_sent', $queryBuilder->createNamedParameter(SendFormat::NONE, PDO::PARAM_INT)),
            )
            ->groupBy('l.mail')
            ->orderBy('m.scheduled', 'DESC')
            ->addOrderBy('m.scheduled_begin', 'DESC')
            ->execute()
            ->fetchAllAssociative()
        );
    }
}
