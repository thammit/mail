<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Updates;

use Doctrine\DBAL\Exception;
use JsonException;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class ImprovedProcessHandlingUpdater implements UpgradeWizardInterface
{
    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'mailImproveProcessHandlingUpdater';
    }

    public function getTitle(): string
    {
        return 'EXT:mail: Improved process handling updater.';
    }

    public function getDescription(): string
    {
        return 'For a better control which recipients of a mailing already handled, and whats the current state of the delivery process ' .
            'several new database field where added. To fix the process bars of already sent mails, this updater calculates them once and ' .
            'write it to the corresponding database fields.';
    }

    /**
     * @return bool
     * @throws Exception
     * @throws JsonException
     */
    public function executeUpdate(): bool
    {
        $connectionMail = $this->getConnectionPool()->getConnectionForTable('tx_mail_domain_model_mail');
        $mails = $this->getMailRecordsToUpdate();
        foreach ($mails as $mail) {
            $recipients = MailerUtility::removeDuplicateValues(json_decode($mail['recipients'],
                true, 3, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY));
            $numberOfRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($recipients);
            $connectionMail->update('tx_mail_domain_model_mail', [
                'number_of_recipients' => $numberOfRecipients,
                'recipients_handled' => json_encode($recipients, JSON_THROW_ON_ERROR, 2),
                'number_of_recipients_handled' => $numberOfRecipients,
                'delivery_progress' => 100,
            ],
                ['uid' => (int)$mail['uid']]
            );
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        return count($this->getMailRecordsToUpdate()) !== 0;
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
    protected function getMailRecordsToUpdate(): array
    {
        $queryBuilder = $this->getPreparedQueryBuilder('tx_mail_domain_model_mail');
        try {
            return $queryBuilder
                ->select('*')
                ->where(
                    $queryBuilder->expr()->eq('sent', 1),
                    $queryBuilder->expr()->eq('delivery_progress', 0)
                )
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Exception $exception) {
            return [];
        }
    }

    protected function getPreparedQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->from($table);
        return $queryBuilder;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

}
