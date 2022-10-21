<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
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

//        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();
//        return $queryBuilder
//            ->select('*')
//            ->from($this->table)
//            ->where(
//                $queryBuilder->expr()->neq('scheduled', 0),
//                $queryBuilder->expr()->lt('scheduled', time()),
//                $queryBuilder->expr()->eq('scheduled_end', 0),
//                $queryBuilder->expr()->notIn('type', [MailType::DRAFT_INTERNAL, MailType::DRAFT_EXTERNAL])
//            )
//            ->orderBy('scheduled')
//            ->execute()
//            ->fetchAssociative();
    }

    /**
     * @param int $pid
     * @return array
     * @throws DBALException
     * @throws Exception
     * todo rebuild query with extbase
     */
    public function findSentByPid(int $pid): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder->selectLiteral('tx_mail_domain_model_mail.uid', 'tx_mail_domain_model_mail.subject', 'tx_mail_domain_model_mail.scheduled', 'tx_mail_domain_model_mail.scheduled_begin', 'tx_mail_domain_model_mail.scheduled_end', 'tx_mail_domain_model_mail.recipients', 'COUNT(tx_mail_domain_model_log.mail) AS count')
            ->from('tx_mail_domain_model_mail', 'tx_mail_domain_model_mail')
            ->leftJoin(
                'tx_mail_domain_model_mail',
                'tx_mail_domain_model_log',
                'tx_mail_domain_model_log',
                $queryBuilder->expr()->eq('tx_mail_domain_model_mail.uid', $queryBuilder->quoteIdentifier('tx_mail_domain_model_log.mail'))
            )
            ->add('where', 'tx_mail_domain_model_mail.pid = ' . $pid .
                ' AND tx_mail_domain_model_mail.type IN (0,1)' .
                ' AND tx_mail_domain_model_mail.sent = 1' .
                ' AND tx_mail_domain_model_log.response_type = 0' .
                ' AND tx_mail_domain_model_log.format_sent > 0')
            ->groupBy('tx_mail_domain_model_log.mail')
            ->orderBy('tx_mail_domain_model_mail.scheduled', 'DESC')
            ->addOrderBy('tx_mail_domain_model_mail.scheduled_begin', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }
}
