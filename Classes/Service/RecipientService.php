<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Database\QueryGenerator;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Enumeration\RecordType;
use MEDIAESSENZ\Mail\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility as MailCsvUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class RecipientService
{
    protected int $pageId = 0;
    protected $pageInfo;
    protected string $backendUserPermissions;
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,accepts_html';
    protected array $allowedTables = ['fe_users', 'tt_address'];
    protected string $userTable = '';

    public function __construct(
        protected GroupRepository $groupRepository,
        protected AddressRepository $addressRepository,
        protected FrontendUserRepository $frontendUserRepository,
        protected EventDispatcherInterface $eventDispatcher
    ) {
        $this->backendUserPermissions = BackendUserUtility::backendUserPermissions();
    }

    /**
     * @param int $pageId
     */
    public function init(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->pageInfo = BackendUtility::readPageAccess($this->pageId, $this->backendUserPermissions);
    }

    /**
     * @param string $userTable
     * @return void
     */
    public function setUserTable(string $userTable): void
    {
        $this->userTable = $userTable;
        $this->allowedTables[] = $userTable;
//        if (array_key_exists('userTable',
//                $this->pageTSConfiguration) && isset($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']]) && is_array($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']])) {
//            $this->userTable = $this->pageTSConfiguration['userTable'];
//            $this->allowedTables[] = $this->userTable;
//        }
    }

    /**
     * @param string|int $userTable
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalSendingGroups(string|int $userTable): array
    {
        $mailGroups = [];
        $groups = $this->groupRepository->findByPid($this->pageId);
        if ($groups) {
            /** @var Group $group */
            foreach ($groups as $group) {
                $result = $this->compileMailGroups([$group], $userTable);
                $totalRecipients = 0;
                if (is_array($result['tt_address'] ?? false)) {
                    $totalRecipients += count($result['tt_address']);
                }
                if (is_array($result['fe_users'] ?? false)) {
                    $totalRecipients += count($result['fe_users']);
                }
                if (is_array($result['PLAINLIST'] ?? false)) {
                    $totalRecipients += count($result['PLAINLIST']);
                }
                if (is_array($result[$userTable] ?? false)) {
                    $totalRecipients += count($result[$userTable]);
                }
                $mailGroups[] = ['uid' => $group->getUid(), 'title' => $group->getTitle(), 'receiver' => $totalRecipients];
            }
        }

        return $mailGroups;
    }

    /**
     * @param array $groups
     * @param string $userTable
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function compileMailGroups(array $groups, string $userTable = ''): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (!count($groups)) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            $recipientList = $this->getUidListOfRecipientsGroupedByTable($group, $userTable);
            $idLists = array_merge_recursive($idLists, $recipientList);
        }

        // Make unique entries
        if (is_array($idLists['tt_address'] ?? false)) {
            $idLists['tt_address'] = array_unique($idLists['tt_address']);
        }

        if (is_array($idLists['fe_users'] ?? false)) {
            $idLists['fe_users'] = array_unique($idLists['fe_users']);
        }

        if (is_array($idLists[$userTable] ?? false) && $userTable) {
            $idLists[$userTable] = array_unique($idLists[$userTable]);
        }

        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($idLists['PLAINLIST']);
        }

        return $idLists;
    }


    /**
     * @param array $groups
     * @param string $userTable
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getQueryInfoIdLists(array $groups, string $userTable = ''): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (count($groups) === 0) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            $recipientList = $this->getUidListOfRecipientsGroupedByTable($group, $userTable);
            $idLists = array_merge_recursive($idLists, $recipientList);
        }

        // Make unique entries
        if (is_array($idLists['tt_address'] ?? false)) {
            $idLists['tt_address'] = array_unique($idLists['tt_address']);
        }

        if (is_array($idLists['fe_users'] ?? false)) {
            $idLists['fe_users'] = array_unique($idLists['fe_users']);
        }

        if (is_array($idLists[$userTable] ?? false) && $userTable) {
            $idLists[$userTable] = array_unique($idLists[$userTable]);
        }

        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($idLists['PLAINLIST']);
        }

        return $idLists;
    }

    /**
     * Get recipient DB record given on the ID
     *
     * @param array $uidListOfRecipients List of recipient IDs
     * @param string $table Table name
     * @param array $fields Field to be selected
     *
     * @return array recipients' data
     * @throws Exception
     * @throws DBALException
     */
    public function getRecipientsDataByUidListAndTable(array $uidListOfRecipients, string $table, array $fields = ['uid','name','email']): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions($table);

        $data = [];
        if (count($uidListOfRecipients)) {
            $res = $queryBuilder
                ->select(...$fields)
                ->from($table)
                ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidListOfRecipients,Connection::PARAM_INT_ARRAY)))
                ->execute();

            while ($row = $res->fetchAssociative()) {
                $data[$row['uid']] = $row;
            }
        }
        return $data;
    }

    /**
     * Return all uid's from $table where the $pid is in $pidList.
     * If $cat is 0 or empty, then all entries (with pid $pid) is returned else only
     * entires which are subscribing to the categories of the group with uid $group_uid is returned.
     * The relation between the recipients in $table and sys_dmail_categories is a true MM relation
     * (Must be correctly defined in TCA).
     *
     * @param string $table The table to select from
     * @param string $pidList The pidList
     * @param int $groupUid The groupUid.
     * @param int $cat The number of relations from tx_mail_domain_model_group to sys_categories
     *
     * @return array The resulting array of uid's
     * @throws Exception
     * @throws DBALException
     */
    public function getIdList(string $table, string $pidList, int $groupUid, int $cat): array
    {
        $addWhere = '';
        $switchTable = $table === 'fe_groups' ? 'fe_users' : $table;
        $pidArray = GeneralUtility::intExplode(',', $pidList, true);
        $queryBuilder = $this->getQueryBuilder($table);

        if ($switchTable === 'fe_users') {
            $addWhere = $queryBuilder->expr()->eq('fe_users.newsletter', 1);
        }

        $mmTable = $GLOBALS['TCA'][$switchTable]['columns']['categories']['config']['MM'];
        if ($cat < 1) {
            if ($table === 'fe_groups') {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($switchTable, $switchTable)
                    ->from($table, $table)
                    ->andWhere(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            } else {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($switchTable)
                    ->andWhere(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            }
        } else {
            if ($table === 'fe_groups') {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
                    ->from('sys_category_record_mm', 'g_mm')
                    ->from('fe_groups', 'fe_groups')
                    ->from($mmTable, 'mm_1')
                    ->leftJoin(
                        'mm_1',
                        $switchTable,
                        $switchTable,
                        $queryBuilder->expr()->eq($switchTable . '.uid', $queryBuilder->quoteIdentifier('mm_1.uid_foreign'))
                    )
                    ->andWhere(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                            ->add($queryBuilder->expr()->eq('mm_1.tablenames', $queryBuilder->createNamedParameter($table)))
                            ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->quoteIdentifier('g_mm.uid_foreign')))
                            ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->createNamedParameter($groupUid, PDO::PARAM_INT)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            } else {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
                    ->from('sys_category_record_mm', 'g_mm')
                    ->from($mmTable, 'mm_1')
                    ->leftJoin(
                        'mm_1',
                        $table,
                        $table,
                        $queryBuilder->expr()->eq($table . '.uid', $queryBuilder->quoteIdentifier('mm_1.uid_foreign'))
                    )
                    ->andWhere(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add($queryBuilder->expr()->eq('mm_1.tablenames', $queryBuilder->createNamedParameter($table)))
                            ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->quoteIdentifier('g_mm.uid_foreign')))
                            ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->createNamedParameter($groupUid, PDO::PARAM_INT)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            }
        }
        $outArr = [];
        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }
        return $outArr;
    }

    /**
     * Return all uid's from $table for a static direct mail group.
     *
     * @param string $table The table to select from
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     * @throws Exception
     * @throws DBALException
     */
    public function getStaticIdListByTableAndGroupUid(string $table, int $uid): array
    {
        $switchTable = $table == 'fe_groups' ? 'fe_users' : $table;

        $queryBuilder = $this->getQueryBuilder($table);

        // fe user group uid should be in list of fe users list of user groups
        // $field = $switchTable.'.usergroup';
        // $command = $table.'.uid';

        // See comment above
        // $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';

        // for fe_users and fe_group, only activated newsletter
        if ($switchTable == 'fe_users') {
            $addWhere = $queryBuilder->expr()->eq(
                $switchTable . '.newsletter',
                1
            );
        }

        if ($table == 'fe_groups') {
            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
                ->innerJoin(
                    'tx_mail_group_mm',
                    'tx_mail_domain_model_group',
                    'tx_mail_domain_model_group',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->innerJoin(
                    'tx_mail_group_mm',
                    $table,
                    $table,
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier($table . '.uid'))
                )
                ->innerJoin(
                    $table,
                    $switchTable,
                    $switchTable,
                    $queryBuilder->expr()->inSet($switchTable . '.usergroup', $queryBuilder->quoteIdentifier($table . '.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)))
                        ->add($addWhere)
                )
                ->orderBy($switchTable . '.uid')
                ->addOrderBy($switchTable . '.email')
                ->execute();
        } else {
            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
                ->innerJoin(
                    'tx_mail_group_mm',
                    'tx_mail_domain_model_group',
                    'tx_mail_domain_model_group',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->innerJoin(
                    'tx_mail_group_mm',
                    $switchTable,
                    $switchTable,
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier($switchTable . '.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($switchTable)))
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)))
                        ->add($addWhere)
                )
                ->orderBy($switchTable . '.uid')
                ->addOrderBy($switchTable . '.email')
                ->execute();
        }

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        if ($table == 'fe_groups') {
            // get the uid of the current fe_group
            $queryBuilder = $this->getQueryBuilder($table);

            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $table . '.uid')
                ->from($table, $table)
                ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
                ->leftJoin(
                    'tx_mail_domain_model_group',
                    'tx_mail_group_mm',
                    'tx_mail_group_mm',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('fe_groups.uid', $queryBuilder->quoteIdentifier('tx_mail_group_mm.uid_foreign')))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                )
                ->execute();

            [$groupId] = $res->fetchAllAssociative();

            // recursively get all subgroups of this fe_group
            if (is_integer($groupId)) {
                $subgroups = $this->getFrontendUserGroupsRecursiveByGroup($groupId);
            }

            if (!empty($subgroups)) {
                $usergroupInList = null;
                foreach ($subgroups as $subgroup) {
                    $usergroupInList .= (($usergroupInList == null) ? null : ' OR') . ' INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',' . intval($subgroup) . ',\') )';
                }
                $usergroupInList = '(' . $usergroupInList . ')';

                // fetch all fe_users from these subgroups
                $queryBuilder = $this->getQueryBuilder($table);
                // for fe_users and fe_group, only activated newsletter
                if ($switchTable == 'fe_users') {
                    $addWhere = $queryBuilder->expr()->eq(
                        $switchTable . '.newsletter',
                        1
                    );
                }

                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($table, $table)
                    ->innerJoin(
                        $table,
                        $switchTable,
                        $switchTable
                    )
                    ->orWhere($usergroupInList)
                    ->andWhere(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();

                while ($row = $res->fetchAssociative()) {
                    $outArr[] = $row['uid'];
                }
            }
        }

        return $outArr;
    }

    /**
     * Construct the array of uid's from $table selected
     * by special query of mail group of such type
     *
     * @param string $table The table to select from
     * @param Group $group
     *
     * @return array The resulting query.
     * @throws \Doctrine\DBAL\Exception|Exception
     */
    public function getSpecialQueryIdList(string $table, Group $group): array
    {
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $outArr = [];
        if ($group->getQuery()) {
            $queryGenerator->init('dmail_queryConfig', $table);
            $queryGenerator->queryConfig = $queryGenerator->cleanUpQueryConfig(unserialize($group->getQuery()));

            $queryGenerator->extFieldLists['queryFields'] = 'uid';
            $select = $queryGenerator->getSelectQuery();
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $recipients = $connection->executeQuery($select)->fetchAllAssociative();

            foreach ($recipients as $recipient) {
                $outArr[] = $recipient['uid'];
            }
        }
        return $outArr;
    }

    /**
     * Get all subsgroups recursively.
     *
     * @param int $groupId Parent fe usergroup
     *
     * @return array The all id of fe_groups
     * @throws Exception
     * @throws DBALException
     */
    protected function getFrontendUserGroupsRecursiveByGroup(int $groupId): array
    {
        // get all subgroups of this fe_group
        // fe_groups having this id in their subgroup field

        $table = 'fe_groups';
        $mmTable = 'tx_mail_group_mm';
        $groupTable = 'tx_mail_domain_model_group';

        $queryBuilder = $this->getQueryBuilder($table);

        $res = $queryBuilder->selectLiteral('DISTINCT fe_groups.uid')
            ->from($table, $table)
            ->join(
                $table,
                $mmTable,
                $mmTable,
                $queryBuilder->expr()->eq(
                    $mmTable . '.uid_local',
                    $queryBuilder->quoteIdentifier($table . '.uid')
                )
            )
            ->join(
                $mmTable,
                $groupTable,
                $groupTable,
                $queryBuilder->expr()->eq(
                    $mmTable . '.uid_local',
                    $queryBuilder->quoteIdentifier($groupTable . '.uid')
                )
            )
            ->andWhere('INSTR( CONCAT(\',\',fe_groups.subgroup,\',\'),\',' . $groupId . ',\' )')
            ->execute();
        $groupArr = [];

        while ($row = $res->fetchAssociative()) {
            $groupArr[] = $row['uid'];

            // add all subgroups recursively too
            $groupArr = array_merge($groupArr, $this->getFrontendUserGroupsRecursiveByGroup($row['uid']));
        }

        return $groupArr;
    }

    /**
     * Put all recipients uid from all table into an array
     *
     * @param Group $mailGroup Uid of the group
     * @param string $userTable
     * @return array List of the uid in an array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function compileMailGroup(Group $mailGroup, string $userTable = ''): array
    {
        $idLists = [];
        if ($mailGroup->getPid() === $this->pageId) {
            switch ($mailGroup->getType()) {
                case RecipientGroupType::PAGES:
                    // From pages
                    // use current page if no else
                    $thePages = $mailGroup->getPages() ?: $this->pageId;
                    // Explode the pages
                    $pages = GeneralUtility::intExplode(',', $thePages);
                    $pageIdArray = [];
                    foreach ($pages as $pageUid) {
                        if ($pageUid > 0) {
                            $pageInfo = BackendUtility::readPageAccess($pageUid, $this->backendUserPermissions);
                            if (is_array($pageInfo)) {
                                $pageIdArray[] = $pageUid;
                                if ($mailGroup->isRecursive()) {
                                    $pageIdArray = array_merge($pageIdArray,
                                        BackendDataUtility::getRecursiveSelect($pageUid, $this->backendUserPermissions));
                                }
                            }
                        }
                    }

                    // Remove any duplicates
                    $pageIdArray = array_unique($pageIdArray);
                    $pidList = implode(',', $pageIdArray);

                    // Make queries
                    if ($pidList) {
                        // tt_address
                        if ($mailGroup->hasAddress()) {
                            $idLists['tt_address'] = $this->getIdList('tt_address', $pidList, $mailGroup->getUid(), $mailGroup->getCategories()->count());
                        }
                        // fe_users
                        if ($mailGroup->hasFrontendUser()) {
                            $idLists['fe_users'] = $this->getIdList('fe_users', $pidList, $mailGroup->getUid(), $mailGroup->getCategories()->count());
                        }
                        // user table
                        if ($userTable && $mailGroup->hasCustom()) {
                            $idLists[$userTable] = $this->getIdList($userTable, $pidList, $mailGroup->getUid(), $mailGroup->getCategories()->count());
                        }
                        // fe_groups
                        if ($mailGroup->hasFrontendUserGroup()) {
                            $idLists['fe_users'] = $this->getIdList('fe_groups', $pidList, $mailGroup->getUid(), $mailGroup->getCategories()->count());
                            $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $idLists['fe_users']));
                        }
                    }
                    break;
                case RecipientGroupType::CSV:
                    // List of mails
                    if ($mailGroup->isCsv()) {
                        $dmCsvUtility = GeneralUtility::makeInstance(MailCsvUtility::class);
                        $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroup->getList()), $this->fieldList);
                    } else {
                        $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup->getList())));
                    }
                    $idLists['tx_mail_domain_model_group'] = RecipientUtility::removeDuplicates($recipients);
                    break;
                case RecipientGroupType::STATIC:
                    // Static MM list
                    $idLists['tt_address'] = $this->getStaticIdListByTableAndGroupUid('tt_address', $mailGroup->getUid());
                    $idLists['fe_users'] = $this->getStaticIdListByTableAndGroupUid('fe_users', $mailGroup->getUid());
                    $tempGroups = $this->getStaticIdListByTableAndGroupUid('fe_groups', $mailGroup->getUid());
                    $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                    if ($userTable) {
                        $idLists[$userTable] = $this->getStaticIdListByTableAndGroupUid($userTable, $mailGroup->getUid());
                    }
                    break;
                case RecipientGroupType::QUERY:
                    // Special query list
                    $mailGroup = $this->updateSpecialQuery($mailGroup, $userTable);
                    $table = '';
                    if ($mailGroup->hasAddress()) {
                        $table = 'tt_address';
                    } else {
                        if ($mailGroup->hasFrontendUser()) {
                            $table = 'fe_users';
                        } else {
                            if ($userTable && $mailGroup->hasCustom()) {
                                $table = $userTable;
                            }
                        }
                    }
                    if ($table) {
                        $idLists[$table] = $this->getSpecialQueryIdList($table, $mailGroup);
                    }
                    break;
                case RecipientGroupType::OTHER:
                    $groups = $this->getAllGroupsRecursive($mailGroup);

                    foreach ($groups as $group) {
                        $collect = $this->compileMailGroup($group);
                        if (is_array($collect['queryInfo']['id_lists'])) {
                            $idLists = array_merge_recursive($idLists, $collect['queryInfo']['id_lists']);
                        }
                    }

                    // Make unique entries
                    if (is_array($idLists['tt_address'])) {
                        $idLists['tt_address'] = array_unique($idLists['tt_address']);
                    }
                    if (is_array($idLists['fe_users'])) {
                        $idLists['fe_users'] = array_unique($idLists['fe_users']);
                    }
                    if (is_array($idLists[$userTable]) && $userTable) {
                        $idLists[$userTable] = array_unique($idLists[$userTable]);
                    }
                    if (is_array($idLists['tx_mail_domain_model_group'])) {
                        $idLists['tx_mail_domain_model_group'] = RecipientUtility::removeDuplicates($idLists['tx_mail_domain_model_group']);
                    }
                    break;
                default:
            }
        }

        /**
         * Hook for cmd_compileMailGroup
         * manipulate the generated id_lists
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] ?? false)) {
            $hookObjectsArr = [];

            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                // todo
                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $mailGroup);
                }
            }

            unset($idLists);
            $idLists = $temporaryList;
        }

        return [
            'queryInfo' => ['id_lists' => $idLists],
        ];
    }

    /**
     * @param Group $mailGroup
     * @param int $recursion
     * @return Group[]
     */
    protected function getAllGroupsRecursive(Group $mailGroup, int $recursion = 0): array
    {
//        $groups = array_unique($tempRepository->getMailGroups($mailGroup->getChildren()), [$mailGroup->getUid()]);
        $groups = [$mailGroup];
        $recursion++;
        if ($recursion > 20) {
            return $groups;
        }
        $childGroups = $mailGroup->getChildren();
        if ($mailGroup->getType() === RecipientGroupType::OTHER && $childGroups->count() > 0) {
            /** @var Group $childGroup */
            foreach ($childGroups as $childGroup) {
                $groups[] = $childGroup->getType() === RecipientGroupType::OTHER && $childGroup->getChildren()->count() > 0 ? $this->getAllGroupsRecursive($childGroup,
                    $recursion) : $childGroup;
            }
        }

        return array_merge(...$groups);
    }

    /**
     * Update recipient list record with a special query
     *
     * @param Group $group
     * @param string $userTable
     * @return Group Updated DB records
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function updateSpecialQuery(Group $group, string $userTable = ''): Group
    {
        // todo
        // $this->set = is_array($parsedBody['csv'] ?? '') ? $parsedBody['csv'] : (is_array($queryParams['csv'] ?? '') ? $queryParams['csv'] : []);
//        $set = $this->set;
        $queryTable = $set['queryTable'] ?? '';
        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');

        $table = '';
        if ($group->hasAddress()) {
            $table = 'tt_address';
        } else {
            if ($group->hasFrontendUser()) {
                $table = 'fe_users';
            } else {
                if ($userTable && $group->hasCustom()) {
                    $table = $userTable;
                }
            }
        }

        $queryTable = $queryTable ?: $table;
        $queryConfig = $queryConfig ? serialize($queryConfig) : $group->getQuery();

        if ($queryTable != $table) {
            $queryConfig = '';
        }

        if ($queryTable != $table || $queryConfig != $group->getQuery()) {
            $recordTypes = 0;
            if ($queryTable == 'tt_address') {
                $recordTypes = RecordType::ADDRESS;
            } else {
                if ($queryTable == 'fe_users') {
                    $recordTypes = RecordType::FRONTEND_USER;
                } else {
                    if ($queryTable == $userTable) {
                        $recordTypes = RecordType::CUSTOM;
                    }
                }
            }

            $group->setRecordTypes($recordTypes);
            $group->setQuery($queryConfig);
            $this->groupRepository->update($group);
            $this->groupRepository->persist();
        }

        return $group;
    }

    /**
     * Fetches recipient IDs from a given group ID
     * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
     *
     * @param Group $group Recipient group ID
     * @param string $userTable
     * @return array List of recipient IDs
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function getUidListOfRecipientsGroupedByTable(Group $group, string $userTable): array
    {
        $idLists = [];

        switch ($group->getType()) {
            case RecipientGroupType::PAGES:
                // From pages
                // use current page if not set in mail group
                $thePages = $group->getPages();
                // Explode the pages
                $pages = GeneralUtility::intExplode(',', $thePages);
                $pageIdArray = [];

                foreach ($pages as $pageUid) {
                    if ($pageUid > 0) {
                        $pageInfo = BackendUtility::readPageAccess($pageUid, $this->backendUserPermissions);
                        if (is_array($pageInfo)) {
                            $pageIdArray[] = $pageUid;
                            if ($group->isRecursive()) {
                                $pageIdArray = array_merge($pageIdArray, BackendDataUtility::getRecursiveSelect($pageUid, $this->backendUserPermissions));
                            }
                        }
                    }
                }
                // Remove any duplicates
                $pageIdArray = array_unique($pageIdArray);
                $pidList = implode(',', $pageIdArray);

                // Make queries
                if ($pidList) {
                    if ($group->hasAddress()) {
                        // tt_address
                        $idLists['tt_address'] = $this->getIdList('tt_address', $pidList, $group->getUid(), $group->getCategories()->count());
                    }
                    if ($group->hasFrontendUser()) {
                        // fe_users
                        $idLists['fe_users'] = $this->getIdList('fe_users', $pidList, $group->getUid(), $group->getCategories()->count());
                    }
                    if ($userTable && $group->hasCustom()) {
                        // user table
                        $idLists[$userTable] = $this->getIdList($userTable, $pidList, $group->getUid(), $group->getCategories()->count());
                    }
                    if ($group->hasFrontendUserGroup()) {
                        // fe_groups
                        $idLists['fe_users'] = $this->getIdList('fe_groups', $pidList, $group->getUid(), $group->getCategories()->count());
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users']));
                    }
                }
                break;
            case RecipientGroupType::CSV:
                // List of mails
                if ($group->isCsv()) {
                    $dmCsvUtility = GeneralUtility::makeInstance(CsvUtility::class);
                    $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($group->getList()));
                } else {
                    $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $group->getList())));
                }
                $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($recipients);
                break;
            case RecipientGroupType::STATIC:
                // Static MM list
                $idLists['tt_address'] = $this->getStaticIdListByTableAndGroupUid('tt_address', $group->getUid());
                $idLists['fe_users'] = $this->getStaticIdListByTableAndGroupUid('fe_users', $group->getUid());
                $tempGroups = $this->getStaticIdListByTableAndGroupUid('fe_groups', $group->getUid());
                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                if ($userTable) {
                    $idLists[$userTable] = $this->getStaticIdListByTableAndGroupUid($userTable, $group->getUid());
                }
                break;
            case RecipientGroupType::QUERY:
                // Special query list
                // Todo Remove that shit!
                $queryTable = GeneralUtility::_GP('SET')['queryTable'];
                $queryConfig = GeneralUtility::_GP('dmail_queryConfig');

                $group = $this->updateMailGroup($group, $userTable, $queryTable, $queryConfig);
                $table = '';
                if ($group->hasAddress()) {
                    $table = 'tt_address';
                } else {
                    if ($group->hasFrontendUser()) {
                        $table = 'fe_users';
                    } else {
                        if ($userTable && $group->hasCustom()) {
                            $table = $userTable;
                        }
                    }
                }
                if ($table) {
                    $idLists[$table] = $this->getSpecialQueryIdList($table, $group);
                }
                break;
            case RecipientGroupType::OTHER:
                $groups = $this->getAllGroupsRecursive($group);
                foreach ($groups as $group) {
                    $collect = $this->getUidListOfRecipientsGroupedByTable($group, $userTable);
                    $idLists = array_merge_recursive($idLists, $collect);
                }
                break;
            default:
        }

        return $idLists;
    }

    /**
     * Update the mailgroup DB record
     *
     * @param Group $group mail group
     * @param string $userTable
     * @param string $queryTable
     * @param $queryConfig
     * @return Group updated mail group
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function updateMailGroup(Group $group, string $userTable, string $queryTable, $queryConfig): Group
    {
        $recordTypes = $group->getRecordTypes();
        $table = '';
        if ($recordTypes & RecordType::ADDRESS) {
            $table = 'tt_address';
        } else {
            if ($recordTypes & RecordType::FRONTEND_USER) {
                $table = 'fe_users';
            } else {
                if ($userTable && ($recordTypes & RecordType::CUSTOM)) {
                    $table = $userTable;
                }
            }
        }

        $queryTable = $queryTable ?: $table;
        $queryConfig = $queryConfig ? serialize($queryConfig) : $group->getQuery();

        if ($queryTable !== $table) {
            $queryConfig = '';
        }

        if ($queryTable !== $table || $queryConfig !== $group->getQuery()) {
            $recordTypes = 0;
            if ($queryTable === 'tt_address') {
                $recordTypes = RecordType::ADDRESS;
            } else {
                if ($queryTable === 'fe_users') {
                    $recordTypes = RecordType::FRONTEND_USER;
                } else {
                    if ($queryTable === $userTable) {
                        $recordTypes = RecordType::CUSTOM;
                    }
                }
            }

            $group->setRecordTypes($recordTypes);
            $group->setQuery($queryConfig);
            $this->groupRepository->update($group);
            $this->groupRepository->persist();
        }

        return $group;
    }

    public function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string|null $table
     * @return \TYPO3\CMS\Core\Database\Connection
     */
    public function getConnection(string $table = null): \TYPO3\CMS\Core\Database\Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table);
    }

    /**
     * @param string|null $table
     * @return QueryBuilder
     */
    public function getQueryBuilder(string $table = null): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table);
    }

    /**
     * @param string|null $table
     * @param bool $withDeleted
     * @return QueryBuilder
     */
    public function getQueryBuilderWithoutRestrictions(string $table = null, bool $withDeleted = false): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        if (!$withDeleted) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }
        return $queryBuilder;
    }

}
