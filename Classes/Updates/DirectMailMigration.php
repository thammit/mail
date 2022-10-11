<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Updates;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class DirectMailMigration implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'directMail2Mail';
    }

    public function getTitle(): string
    {
        return 'Migrate EXT:direct_mail database tables to EXT:mail format.';
    }

    public function getDescription(): string
    {
        return 'Migrate EXT:direct_mail database tables to EXT:mail format  (sys_dmail -> tx_mail_domain_model_mail; sys_dmail_group -> tx_mail_domain_model_group; sys_dmail_maillog -> tx_mail_domain_model_log; sys_dmail_category -> sys_category)';
    }

    /**
     * @return bool
     * @throws DBALException
     * @throws Exception
     */
    public function executeUpdate(): bool
    {
        // sys_dmail -> tx_mail_domain_model_mail
        $connectionMail = $this->getConnectionPool()->getConnectionForTable('tx_mail_domain_model_mail');
        foreach ($this->getSysDmailRecordsToMigrate() as $record) {
            $alreadyMigrated = $connectionMail->count('*', 'tx_mail_domain_model_mail', ['uid' => $record['uid']]);
            if ($alreadyMigrated === 0) {
                $connectionMail->insert('tx_mail_domain_model_mail',
                    [
                        'uid' => $record['uid'],
                        'pid' => $record['pid'],
                        'tstamp' => $record['tstamp'],
                        'type' => $record['type'],
                        'sys_language_uid' => $record['sys_language_uid'],
                        'subject' => $record['subject'],
                        'reply_to_email' => $record['replyto_email'],
                        'reply_to_name' => $record['replyto_name'],
                        'deleted' => $record['deleted'],
                        'page' => $record['page'],
                        'attachment' => $record['attachment'],
                        'from_email' => $record['from_email'],
                        'from_name' => $record['from_name'],
                        'organisation' => $record['organisation'],
                        'priority' => $record['priority'],
                        'encoding' => $record['encoding'],
                        'charset' => $record['charset'],
                        'send_options' => $record['sendOptions'],
                        'include_media' => $record['includeMedia'],
                        'flowed_format' => $record['flowedFormat'],
                        'html_params' => $record['HTMLParams'],
                        'plain_params' => $record['plainParams'],
                        'sent' => $record['issent'],
                        'rendered_size' => $record['renderedsize'],
                        'mail_content' => $record['mailContent'],
                        'query_info' => $record['query_info'],
                        'scheduled' => $record['scheduled'],
                        'scheduled_begin' => $record['scheduled_begin'],
                        'scheduled_end' => $record['scheduled_end'],
                        'return_path' => $record['return_path'],
                        'redirect' => $record['use_rdct'],
                        'redirect_all' => $record['long_link_mode'],
                        'redirect_url' => $record['long_link_rdct_url'],
                        'auth_code_fields' => $record['authcode_fieldList'],
                        'recipient_groups' => $record['recipientGroups'],
                    ]);
            }
        }

        // sys_dmail_group -> tx_mail_domain_model_group
        $connectionGroup = $this->getConnectionPool()->getConnectionForTable('tx_mail_domain_model_group');
        foreach ($this->getSysDmailGroupRecordsToMigrate() as $record) {
            $alreadyMigrated = $connectionGroup->count('*', 'tx_mail_domain_model_group', ['uid' => $record['uid']]);
            if ($alreadyMigrated === 0) {
                $connectionGroup->insert('tx_mail_domain_model_group',
                    [
                        'uid' => $record['uid'],
                        'pid' => $record['pid'],
                        'tstamp' => $record['tstamp'],
                        'deleted' => $record['deleted'],
                        'type' => $record['type'],
                        'title' => $record['title'],
                        'description' => $record['description'],
                        'query' => $record['query'],
                        'static_list' => $record['static_list'],
                        'list' => $record['list'],
                        'csv' => $record['csv'],
                        'pages' => $record['pages'],
                        'record_types' => $record['whichtables'],
                        'recursive' => $record['recursive'],
                        'children' => $record['mail_groups'],
                        'categories' => $record['select_categories'],
                        'sys_language_uid' => $record['sys_language_uid'],
                    ]);
            }
        }

        // sys_dmail_maillog -> tx_mail_domain_model_log
        $connectionLog = $this->getConnectionPool()->getConnectionForTable('tx_mail_domain_model_log');
        foreach ($this->getSysDmailLogRecordsToMigrate() as $record) {
            $alreadyMigrated = $connectionLog->count('*', 'tx_mail_domain_model_log', ['uid' => $record['uid']]);
            if ($alreadyMigrated === 0) {
                $recipientTable = $record['rtbl'] === 'f' ? 'fe_users' : ($record['rtbl'] === 't' ? 'tt_address' : 'tx_mail_domain_model_recipient');
                $connectionLog->insert('tx_mail_domain_model_log',
                    [
                        'uid' => $record['uid'],
                        'mail' => $record['mid'],
                        'recipient' => $recipientTable . '_' . $record['rid'],
                        'recipient_table' => $record['rtbl'],
                        'recipient_uid' => $record['rid'],
                        'email' => $record['email'],
                        'tstamp' => $record['tstamp'],
                        'url' => $record['url'],
                        'size' => $record['size'],
                        'parse_time' => $record['parsetime'],
                        'response_type' => $record['response_type'],
                        'format_sent' => $record['html_sent'],
                        'url_id' => $record['url_id'],
                        'return_content' => $record['return_content'],
                        'return_code' => $record['return_code'],
                    ]);
            }
        }

        // sys_dmail_category -> sys_category
        // todo

        return true;
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        return $this->hasSysDmailRecordsToMigrate() || $this->hasSysDmailGroupRecordsToMigrate() || $this->hasSysDmailLogRecordsToMigrate();
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    protected function hasSysDmailRecordsToMigrate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder('sys_dmail');
        return (bool)$queryBuilder
            ->count('uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    protected function hasSysDmailGroupRecordsToMigrate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder('sys_dmail_group');
        return (bool)$queryBuilder
            ->count('uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    protected function hasSysDmailLogRecordsToMigrate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder('sys_dmail_maillog');
        return (bool)$queryBuilder
            ->count('uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function getSysDmailRecordsToMigrate(): array
    {
        return $this->getPreparedQueryBuilder('sys_dmail')->select('*')->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function getSysDmailGroupRecordsToMigrate(): array
    {
        return $this->getPreparedQueryBuilder('sys_dmail_group')->select('*')->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function getSysDmailLogRecordsToMigrate(): array
    {
        return $this->getPreparedQueryBuilder('sys_dmail_maillog')->select('*')->executeQuery()->fetchAllAssociative();
    }

    protected function getPreparedQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->from($table);
        return $queryBuilder;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
