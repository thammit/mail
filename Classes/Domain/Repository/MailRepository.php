<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\MailStatus;
use MEDIAESSENZ\Mail\Type\Enumeration\MailType;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use TYPO3\CMS\Core\Database\Connection;
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

    public function initializeObject(): void
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
            $query->logicalAnd(
                $query->equals('status', MailStatus::DRAFT),
                $query->equals('pid', $pid),
            )
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
            $query->logicalAnd(
                $query->equals('status', MailStatus::DRAFT),
                $query->equals('page', $page),
                $query->equals('pid', $pid),
            )
        );
        return $query->execute();
    }

    public function findScheduledByPid(int $pid, int $limit = 10): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('pid', $pid),
                $query->logicalOr(
                    $query->equals('status', MailStatus::SCHEDULED),
                    $query->equals('status', MailStatus::SENDING),
                    $query->equals('status', MailStatus::PAUSED)
                )
            )
        );
        $query->setOrderings(['scheduled' => QueryInterface::ORDER_DESCENDING]);

        if ($limit) {
            $query->setLimit($limit);
        }
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
            $query->logicalAnd(
                $query->logicalOr(
                    $query->equals('status', MailStatus::SCHEDULED),
                    $query->equals('status', MailStatus::SENDING)
                ),
                $query->logicalNot($query->equals('scheduled', 0)),
                $query->lessThan('scheduled', new DateTimeImmutable('now')),
                $query->equals('scheduledEnd', 0),
                $query->logicalNot($query->in('type', [MailType::DRAFT_INTERNAL, MailType::DRAFT_EXTERNAL]))
            )
        );
        $query->setOrderings(['scheduled' => QueryInterface::ORDER_ASCENDING]);

        return $query->execute()->getFirst();
    }

    /**
     * @param int $pid
     * @return array
     * @throws Exception
     */
    public function findSentByPid(int $pid): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return GeneralUtility::makeInstance(DataMapper::class)->map(Mail::class, $queryBuilder
            ->selectLiteral(
            'm.*'
            )
            ->from('tx_mail_domain_model_mail', 'm')
            ->leftJoin(
                'm',
                'tx_mail_domain_model_log',
                'l',
                $queryBuilder->expr()->eq('m.uid', $queryBuilder->quoteIdentifier('l.mail'))
            )
            ->where(
                $queryBuilder->expr()->eq('m.pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('m.type', $queryBuilder->createNamedParameter([MailType::INTERNAL, MailType::EXTERNAL], Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('m.status', MailStatus::SENDING),
                    $queryBuilder->expr()->eq('m.status', MailStatus::SENT),
                ),
                $queryBuilder->expr()->eq('l.response_type', $queryBuilder->createNamedParameter(ResponseType::ALL, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('l.format_sent', $queryBuilder->createNamedParameter(SendFormat::NONE, Connection::PARAM_INT)),
            )
            ->groupBy('l.mail')
            ->orderBy('m.scheduled', 'DESC')
            ->addOrderBy('m.scheduled_begin', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative()
        );
    }
}
