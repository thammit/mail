<?php
namespace MEDIAESSENZ\Mail\Updates;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class CsvGroupConverter implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'mailCsvGroupConverter';
    }

    public function getTitle(): string
    {
        return 'EXT:mail: Migrate mail group type plain to plain or new group csv, depending on csv type.';
    }

    public function getDescription(): string
    {
        return 'Migrate mail group type plain to plain or new group csv, depending on csv type.';
    }

    /**
     * @throws Exception
     */
    public function executeUpdate(): bool
    {
        if ($this->hasRecordsToMigrate()) {
            $queryBuilder = $this->getPreparedQueryBuilder('tx_mail_domain_model_group');
            $queryBuilder
                ->update('tx_mail_domain_model_group')
                ->set('type', RecipientGroupType::CSV)
                ->set('csv_type', CsvType::PLAIN)
                ->set('csv_data', 'list', false)
                ->set('list', '')
                ->where(
                    $queryBuilder->expr()->eq('type', RecipientGroupType::PLAIN),
                    $queryBuilder->expr()->eq('csv', 1)
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
        if (!$this->tableExists('tx_mail_domain_model_group') || !$this->tableColumnExists('tx_mail_domain_model_group', 'csv')) {
            return false;
        }
        $queryBuilder = $this->getPreparedQueryBuilder('tx_mail_domain_model_group');
        return (bool)$queryBuilder
            ->count('uid')
            ->where(
                $queryBuilder->expr()->eq('type', RecipientGroupType::PLAIN),
                $queryBuilder->expr()->eq('csv', 1)
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
