<?php
namespace MEDIAESSENZ\Mail\Updates;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Type\Enumeration\MailStatus;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class AddStatus implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'mailAddStatus';
    }

    public function getTitle(): string
    {
        return 'EXT:mail: Set new status value depending of mail fields scheduled, delivery_progress and sent.';
    }

    public function getDescription(): string
    {
        return 'Set new status value depending of mail fields scheduled, delivery_progress and sent. Field "sent" can be deleted after running this wizard.';
    }

    /**
     * @throws Exception
     */
    public function executeUpdate(): bool
    {
        if ($this->hasRecordsToMigrate()) {
            $queryBuilderDraft = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
            $queryBuilderDraft
                ->update('tx_mail_domain_model_mail')
                ->set('status', MailStatus::DRAFT)
                ->set('sent', 2)
                ->where(
                    $queryBuilderDraft->expr()->eq('scheduled', 0),
                    $queryBuilderDraft->expr()->eq('sent', 0)
                )
                ->executeStatement();
            $queryBuilderScheduled = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
            $queryBuilderScheduled
                ->update('tx_mail_domain_model_mail')
                ->set('status', MailStatus::SCHEDULED)
                ->set('sent', 3)
                ->where(
                    $queryBuilderScheduled->expr()->neq('scheduled', 0),
                    $queryBuilderScheduled->expr()->eq('sent', 0)
                )
                ->executeStatement();
            $queryBuilderSending = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
            $queryBuilderSending
                ->update('tx_mail_domain_model_mail')
                ->set('status', MailStatus::SENDING)
                ->set('sent', 4)
                ->where(
                    $queryBuilderSending->expr()->neq('scheduled', 0),
                    $queryBuilderSending->expr()->gt('delivery_progress', 0),
                    $queryBuilderSending->expr()->lt('delivery_progress', 100),
                    $queryBuilderSending->expr()->eq('sent', 0)
                )
                ->executeStatement();
            $queryBuilderSent = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
            $queryBuilderSent
                ->update('tx_mail_domain_model_mail')
                ->set('status', MailStatus::SENT)
                ->set('sent', 5)
                ->where(
                    $queryBuilderSent->expr()->neq('scheduled', 0),
                    $queryBuilderSent->expr()->eq('sent', 1)
                )
                ->executeStatement();
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        return $this->hasRecordsToMigrate();
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * @throws Exception
     */
    protected function hasRecordsToMigrate(): bool
    {
        if (!$this->tableExists('tx_mail_domain_model_mail') || !$this->tableColumnExists('tx_mail_domain_model_mail', 'sent')) {
            return false;
        }
        $queryBuilder = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
        return (bool)$queryBuilder
            ->count('uid')
            ->where(
                $queryBuilder->expr()->lte('sent', 1),
            )
            ->executeQuery()
            ->fetchOne();
    }

    protected function getPreparedQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->from($table);
        return $queryBuilder;
    }

    /**
     * @throws Exception
     */
    protected function tableExists(string $table): bool
    {
        return $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->getSchemaManager()
            ->tablesExist([$table]);
    }

    /**
     * @throws Exception
     */
    protected function tableColumnExists(string $table, string $column): bool
    {
        return $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->getSchemaManager()
            ->listTableDetails($table)
            ->hasColumn($column);
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
