<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class SysDmailGroupRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_group';

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
    public function selectSysDmailGroupForFinalMail(int $pid, int $sysLanguageUid, string $defaultSortBy): array
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
     * @param string $intList
     * @param string $permsClause
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailGroupForTestmail(string $intList, string $permsClause): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder
            ->select($this->table . '.*')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'pages',
                'pages',
                $queryBuilder->expr()->eq($this->table . '.pid', $queryBuilder->quoteIdentifier('pages.uid'))
            )
            ->add('where', $this->table . '.uid IN (' . $intList . ')' .
                ' AND ' . $permsClause)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * Update the mailgroup DB record
     *
     * @param array $mailGroup Mailgroup DB record
     * @param string $userTable
     * @param string $queryTable
     * @param $queryConfig
     * @return array Mailgroup DB record after updated
     */
    public function updateMailGroup(array $mailGroup, string $userTable, string $queryTable, $queryConfig): array
    {
//        $set = GeneralUtility::_GP('SET');
//        $queryTable = $set['queryTable'];
//        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');

        $whichTables = (int)$mailGroup['whichtables'];
        $table = '';
        if ($whichTables & 1) {
            $table = 'tt_address';
        } else if ($whichTables & 2) {
            $table = 'fe_users';
        } else if ($userTable && ($whichTables & 4)) {
            $table = $userTable;
        }

        $settings['queryTable'] = $queryTable ?: $table;
        $settings['queryConfig'] = $queryConfig ? serialize($queryConfig) : $mailGroup['query'];

        if ($settings['queryTable'] != $table) {
            $settings['queryConfig'] = '';
        }

        if ($settings['queryTable'] != $table || $settings['queryConfig'] != $mailGroup['query']) {
            $whichTables = 0;
            if ($settings['queryTable'] == 'tt_address') {
                $whichTables = 1;
            } else if ($settings['queryTable'] == 'fe_users') {
                $whichTables = 2;
            } else if ($settings['queryTable'] == $userTable) {
                $whichTables = 4;
            }
            $updateFields = [
                'whichtables' => $whichTables,
                'query' => $settings['queryConfig'],
            ];

            $connection = $this->getConnection($this->table);

            $connection->update(
                $this->table, // table
                $updateFields,
                ['uid' => intval($mailGroup['uid'])] // where
            );
            $mailGroup = BackendUtility::getRecord($this->table, $mailGroup['uid']);
        }
        return $mailGroup;
    }


}
