<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class SysDmailGroupRepository extends AbstractRepository
{
    protected string $table = 'tx_mail_domain_model_group';

    /**
     * @param int $pid
     * @param string $defaultSortBy
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailGroupByPid(int $pid, string $defaultSortBy): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder->select('uid', 'pid', 'title', 'description', 'type')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    $defaultSortBy
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pid
     * @param int $sysLanguageUid
     * @param string $defaultSortBy
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findSysDmailGroupUidsForFinalMail(int $pid, int $sysLanguageUid, string $defaultSortBy): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder->select('uid', 'pid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT))
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    '-1, ' . $sysLanguageUid
                )
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    $defaultSortBy
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param array $uids
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByUids(array $uids, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'p',
                $queryBuilder->expr()->eq($this->table . '.pid', $queryBuilder->quoteIdentifier('p.uid'))
            )
            ->where(
                $queryBuilder->expr()->in(
                    $this->table . '.uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->andWhere($permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * Update the mailgroup DB record
     *
     * @param array $group Mailgroup DB record
     * @param string $userTable
     * @param string $queryTable
     * @param $queryConfig
     * @return array Mailgroup DB record after updated
     */
    public function updateMailGroup(array $group, string $userTable, string $queryTable, $queryConfig): array
    {
        $recordTypes = (int)$group['record_types'];
        $table = '';
        if ($recordTypes & 1) {
            $table = 'tt_address';
        } else if ($recordTypes & 2) {
            $table = 'fe_users';
        } else if ($userTable && ($recordTypes & 4)) {
            $table = $userTable;
        }

        $settings['queryTable'] = $queryTable ?: $table;
        $settings['queryConfig'] = $queryConfig ? serialize($queryConfig) : $group['query'];

        if ($settings['queryTable'] != $table) {
            $settings['queryConfig'] = '';
        }

        if ($settings['queryTable'] != $table || $settings['queryConfig'] != $group['query']) {
            $recordTypes = 0;
            if ($settings['queryTable'] == 'tt_address') {
                $recordTypes = 1;
            } else if ($settings['queryTable'] == 'fe_users') {
                $recordTypes = 2;
            } else if ($settings['queryTable'] == $userTable) {
                $recordTypes = 4;
            }
            $updateFields = [
                'record_types' => $recordTypes,
                'query' => $settings['queryConfig'],
            ];

            $this->update((int)$group['uid'], $updateFields);

            $group = BackendUtility::getRecord($this->table, $group['uid']);
        }
        return $group;
    }

    /**
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function update(int $uid, array $updateData): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table,
            $updateData,
            ['uid' => $uid]
        );
    }

}
