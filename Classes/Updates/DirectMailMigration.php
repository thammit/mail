<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Updates;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\RecordType;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
        return 'Migrate EXT:direct_mail database tables to EXT:mail format  (sys_dmail -> tx_mail_domain_model_mail; '
            . 'sys_dmail_group -> tx_mail_domain_model_group; sys_dmail_maillog -> tx_mail_domain_model_log; sys_dmail_category -> sys_category). '
            . 'Please update reference index afterwards, to update number of category relations';
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
                $mailContent = unserialize(base64_decode($record['mailContent']));
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
                        'html_params' => $record['HTMLParams'],
                        'plain_params' => $record['plainParams'],
                        'sent' => $record['issent'],
                        'rendered_size' => strlen($mailContent['html']['content']) + strlen($mailContent['plain']['content']),
                        'message_id' => $mailContent['messageid'],
                        'html_content' => $mailContent['html']['content'] ?? '',
                        'plain_content' => $mailContent['plain']['content'] ?? '',
                        'html_links' => json_encode($mailContent['html']['href'] ?? []),
                        'plain_links' => json_encode($mailContent['plain']['link_ids'] ?? []),
                        'recipients' => json_encode(unserialize($record['query_info'])['id_lists'] ?? []),
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
        $whichtablesToRecipientSources = [
            0b00000000 => '',
            0b00000001 => 'tt_address',
            0b00000010 => 'fe_users',
            0b00000011 => 'tt_address,fe_users',
            0b00000100 => '',
            0b00000101 => 'tt_address',
            0b00000110 => 'fe_users',
            0b00000111 => 'tt_address,fe_users',
            0b00001000 => 'fe_groups',
            0b00001001 => 'fe_groups,tt_address',
            0b00001010 => 'fe_groups,fe_users',
            0b00001011 => 'fe_groups,tt_address,fe_users',
            0b00001100 => 'fe_groups',
            0b00001101 => 'fe_groups,tt_address',
            0b00001110 => 'fe_groups,fe_users',
            0b00001111 => 'fe_groups,tt_address,fe_users',
        ];
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
                        'static_list' => $record['static_list'],
                        'list' => $record['list'],
                        'csv' => $record['csv'],
                        'pages' => $record['pages'],
                        'recipient_sources' => $whichtablesToRecipientSources[$record['whichtables']],
                        'recursive' => $record['recursive'],
                        'children' => $record['mail_groups'],
                        'categories' => $record['select_categories'],
                    ]);
            }
        }

        // sys_dmail_maillog -> tx_mail_domain_model_log
        $connectionLog = $this->getConnectionPool()->getConnectionForTable('tx_mail_domain_model_log');
        foreach ($this->getSysDmailLogRecordsToMigrate() as $record) {
            $alreadyMigrated = $connectionLog->count('*', 'tx_mail_domain_model_log', ['uid' => $record['uid']]);
            if ($alreadyMigrated === 0) {
                // Change plain and html format to match send options of mail table
                $formatSent = match ($record['html_sent']) {
                    1 => SendFormat::HTML,
                    2 => SendFormat::PLAIN,
                    3 => SendFormat::BOTH,
                    default => SendFormat::NONE,
                };
                $table = match ($record['rtbl']) {
                    't' => 'tt_address',
                    'f' => 'fe_users',
                    'P' => 'tx_mail_domain_model_group',
                    default => $record['rtbl'],
                };
                $connectionLog->insert('tx_mail_domain_model_log',
                    [
                        'uid' => $record['uid'],
                        'mail' => $record['mid'],
                        'recipient_table' => $table,
                        'recipient_uid' => $record['rid'],
                        'email' => $record['email'],
                        'tstamp' => $record['tstamp'],
                        'url' => $record['url'],
                        'parse_time' => $record['parsetime'],
                        'response_type' => $record['response_type'],
                        'format_sent' => $formatSent,
                        'url_id' => $record['url_id'],
                        'return_content' => json_encode(unserialize($record['return_content']) ?? []),
                        'return_code' => $record['return_code'],
                    ]);
            }
        }

        // sys_dmail_category -> sys_category
        $connectionCategory = $this->getConnectionPool()->getConnectionForTable('sys_category');
        try {
            $directMailCategorySysCategoryMappings = $this->getRelations((string)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('mail',
                'directMailCategorySysCategoryMapping'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
            $directMailCategorySysCategoryMappings = [];
        }
        try {
            $directMailCategorySysCategoryParentCategory = (int)(GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('mail',
                'directMailCategorySysCategoryParentCategory'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
            $directMailCategorySysCategoryParentCategory = 0;
        }


        foreach ($this->getSysDmailCategoryRecordsToMigrate() as $record) {
            $sysCategoryUid = $directMailCategorySysCategoryMappings[$record['uid']] ?? 0;
            $sysCategoryExists = $connectionCategory->count('*', 'sys_category', ['uid' => $sysCategoryUid]);
            if ($sysCategoryExists === 0) {
                // Add sys_category
                $connectionCategory->insert('sys_category', [
                    'uid' => $sysCategoryUid,
                    'pid' => $record['pid'],
                    'cruser_id' => $record['cruser_id'],
                    'tstamp' => time(),
                    'crdate' => time(),
                    'title' => $record['category'],
                    'parent' => $directMailCategorySysCategoryParentCategory ?? 0,
                    'deleted' => $record['deleted'],
                    'hidden' => $record['hidden'],
                    'sorting' => $record['sorting'],
                    'sys_language_uid' => $record['sys_language_uid'],
                    'l10n_parent' => $record['l18n_parent'],
                ]);
                if ($sysCategoryUid === 0) {
                    $directMailCategorySysCategoryMappings[$record['uid']] = $connectionCategory->lastInsertId();
                }
            }
        }

        $mmTables = [
            'sys_dmail_feuser_category_mm' => [
                'tablenames' => 'fe_users',
                'fieldname' => 'categories',
            ],
            'sys_dmail_group_category_mm' => [
                'tablenames' => 'tx_mail_domain_model_group',
                'fieldname' => 'categories',
            ],
            'sys_dmail_ttaddress_category_mm' => [
                'tablenames' => 'tt_address',
                'fieldname' => 'categories',
            ],
            'sys_dmail_ttcontent_category_mm' => [
                'tablenames' => 'tt_content',
                'fieldname' => 'categories',
            ],
        ];

        $connectionSysCategoryRecordMm = $this->getConnectionPool()->getConnectionForTable('sys_category_record_mm');
        foreach ($mmTables as $table => $config) {
            $mmRecords = $this->getPreparedQueryBuilder($table)->select('*')->executeQuery()->fetchAllAssociative();
            foreach ($mmRecords as $record) {
                $sysCategoryUid = $directMailCategorySysCategoryMappings[$record['uid_foreign']];
                $connectionSysCategoryRecordMm->insert('sys_category_record_mm',
                    [
                        'uid_local' => $record['uid_local'],
                        'uid_foreign' => $sysCategoryUid,
                        'tablenames' => $config['tablenames'],
                        'sorting' => $record['sorting'],
                        'sorting_foreign' => $record['sorting_foreign'],
                        'fieldname' => $config['fieldname'],
                    ]);
            }
        }

        // sys_dmail_group_mm -> tx_mail_group_mm
        $connectionMailGroupMm = $this->getConnectionPool()->getConnectionForTable('tx_mail_group_mm');
        $sysDmailGroupMmRecords = $this->getPreparedQueryBuilder('sys_dmail_group_mm')->select('*')->executeQuery()->fetchAllAssociative();
        foreach ($sysDmailGroupMmRecords as $record) {
            $connectionMailGroupMm->insert('tx_mail_group_mm', [
                'uid_local' => $record['uid_local'],
                'uid_foreign' => $record['uid_foreign'],
                'tablenames' => $record['tablenames'],
                'sorting' => $record['sorting'],
                'sorting_foreign' => $record['sorting_foreign'],
            ]);
        }

        // copy fe_users.module_sys_dmail_newsletter -> newsletter
        // copy fe_users.module_sys_dmail_html -> mail_html
        // copy fe_users.module_sys_dmail_category -> categories (or reference index update ?)
        $connectionFrontendUsers = $this->getConnectionPool()->getConnectionForTable('fe_users');
        $frontendUserRecords = $this->getPreparedQueryBuilder('fe_users')->select('*')->executeQuery()->fetchAllAssociative();
        foreach ($frontendUserRecords as $record) {
            $connectionFrontendUsers->update('fe_users', [
                'mail_active' => $record['module_sys_dmail_newsletter'],
                'mail_html' => $record['module_sys_dmail_html'],
            ],
                ['uid' => (int)$record['uid']]
            );
        }

        // copy tt_address.module_sys_dmail_html -> mail_html
        // copy tt_address.module_sys_dmail_category -> categories (or reference index update ?)
        $connectionAddresses = $this->getConnectionPool()->getConnectionForTable('tt_address');
        $AddressRecords = $this->getPreparedQueryBuilder('tt_address')->select('*')->executeQuery()->fetchAllAssociative();
        foreach ($AddressRecords as $record) {
            $connectionAddresses->update('tt_address', [
                'mail_active' => 1,
                'mail_html' => $record['module_sys_dmail_html'],
            ],
                ['uid' => (int)$record['uid']]
            );
        }

        return true;
    }

    /**
     * @param string $relationsCsv
     * @return array
     */
    public function getRelations(string $relationsCsv): array
    {
        $relations = [];
        $relationsArray = GeneralUtility::trimExplode(',', $relationsCsv, true);
        foreach ($relationsArray as $relationPair) {
            $relationPairArray = GeneralUtility::intExplode(':', $relationPair, true);
            $relations[$relationPairArray[0]] = $relationPairArray[1];
        }

        return $relations;
    }


    /**
     * @throws DBALException
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        return $this->hasSysDmailRecordsToMigrate() || $this->hasSysDmailGroupRecordsToMigrate() || $this->hasSysDmailLogRecordsToMigrate() || $this->hasSysDmailCategoryRecordsToMigrate();
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
     * @throws Exception
     * @throws DBALException
     */
    protected function hasSysDmailCategoryRecordsToMigrate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder('sys_dmail_category');
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

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function getSysDmailCategoryRecordsToMigrate(): array
    {
        return $this->getPreparedQueryBuilder('sys_dmail_category')->select('*')->executeQuery()->fetchAllAssociative();
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
