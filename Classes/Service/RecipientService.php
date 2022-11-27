<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Database\QueryGenerator;
use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\DebugQueryTrait;
use MEDIAESSENZ\Mail\Type\Enumeration\RecordType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Category\Collection\CategoryCollection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class RecipientService
{
    use DebugQueryTrait;

    protected string $backendUserPermissions;
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,accepts_html';
    protected array $allowedTables = ['fe_users', 'tt_address'];

    public function __construct(
        protected GroupRepository          $groupRepository,
        protected AddressRepository        $addressRepository,
        protected FrontendUserRepository   $frontendUserRepository,
        protected EventDispatcherInterface $eventDispatcher
    )
    {
        $this->backendUserPermissions = BackendUserUtility::backendUserPermissions();
    }

    /**
     * @param int $pageId
     * @param string $userTable
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalSendingGroups(int $pageId, string $userTable = ''): array
    {
        $mailGroups = [];
        $groups = $this->groupRepository->findByPid($pageId);
        if ($groups->count() > 0) {
            /** @var Group $group */
            foreach ($groups as $group) {
                $mailGroups[] = [
                    'uid' => $group->getUid(),
                    'title' => $group->getTitle(),
                    'receiver' => RecipientUtility::calculateTotalRecipientsOfUidLists($this->getRecipientsUidListsGroupedByTable($group, $userTable), $userTable)
                ];
            }
        }

        return $mailGroups;
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
    public function getRecipientsDataByUidListAndTable(array $uidListOfRecipients, string $table, array $fields = ['uid', 'name', 'email']): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions($table);

        $data = [];
        if (count($uidListOfRecipients)) {
            $res = $queryBuilder
                ->select(...$fields)
                ->from($table)
                ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidListOfRecipients, Connection::PARAM_INT_ARRAY)))
                ->execute();

            while ($row = $res->fetchAssociative()) {
                $data[$row['uid']] = $row;
            }
        }
        return $data;
    }

    /**
     * Get recipient DB record given on the ID
     *
     * @param array $uidListOfRecipients List of recipient IDs
     * @param string $modelName model name
     * @param array $fields Field to be selected
     * @param bool $useEnhancedModel set to true if data from enhanced model should be used
     *
     * @return array recipients' data
     * @throws InvalidQueryException
     */
    public function getRecipientsDataByUidListAndModelName(array $uidListOfRecipients, string $modelName, array $fields = ['uid', 'name', 'email'], bool $useEnhancedModel = false): array
    {
        $data = [];
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $query = $persistenceManager->createQueryForType($modelName);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setLanguageOverlayMode(false);
        $query->matching(
            $query->in('uid', $uidListOfRecipients)
        );
        $debugResult = $this->debugQuery($query);
        $recipients = $query->execute();
        ViewUtility::addFlashMessageInfo($debugResult, 'Count ' . $recipients->count(), true);

        foreach ($recipients as $recipient) {
            if ($recipient instanceof RecipientInterface) {
                $values = $this->getRecipientModelData($recipient, $fields);
                $data[$recipient->getUid()] = $values;
                if ($useEnhancedModel && $recipient->getRecordIdentifier() !== get_class($recipient) . ':' . $recipient->getUid()) {
                    [$modelName, $uid] = explode(':', $recipient->getRecordIdentifier());
                    $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName($modelName);
                    $repository = GeneralUtility::makeInstance($repositoryName);
                    $recipient = $repository->findByUid($uid);
                    $values = $this->getRecipientModelData($recipient, $fields);
                    $data[$recipient->getUid()] += $values;
                }
            }
        }

        return $data;
    }

    protected function getRecipientModelData(RecipientInterface|DomainObjectInterface $recipient, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (str_contains($field, '_')) {
                // convert snake_case field name to camelCase
                $camelCaseFieldName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));
                $getter = 'get' . ucfirst($camelCaseFieldName);
            }
            if (method_exists($recipient, $getter)) {
                $value = $recipient->$getter();
                if ($value instanceof ObjectStorage) {
                    if ($value->count() > 0) {
                        $titles = [];
                        foreach ($value as $item) {
                            if (method_exists($item, 'getTitle')) {
                                $titles[] = $item->getTitle();
                            } else if (method_exists($item, 'getName')) {
                                $titles[] = $item->getName();
                            }
                        }
                        $values[$field] = implode(', ', $titles);
                    } else {
                        $values[$field] = '';
                    }
                } else {
                    $values[$field] = $value;
                }
            }
            $getter = 'is' . ucfirst($camelCaseFieldName ?? $field);
            if (method_exists($recipient, $getter)) {
                $values[$field] = $recipient->$getter() ? '1' : '0';
            }
        }
        return $values;
    }

    /**
     * @param array $groups
     * @param string $userTable
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getRecipientsUidListsGroupedByTables(array $groups, string $userTable = ''): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (count($groups) === 0) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            $recipientList = $this->getRecipientsUidListsGroupedByTable($group, $userTable);
            $idLists = array_merge_recursive($idLists, $recipientList);
        }

        foreach ($idLists as $table => $idList) {
            if ($table === 'tx_mail_domain_model_group') {
                $idLists[$table] = RecipientUtility::removeDuplicates($idList);
            } else {
                $idLists[$table] = array_unique($idList);
            }
        }

        // Make unique entries
//        if (is_array($idLists['tt_address'] ?? false)) {
//            $idLists['tt_address'] = array_unique($idLists['tt_address']);
//        }
//
//        if (is_array($idLists['fe_users'] ?? false)) {
//            $idLists['fe_users'] = array_unique($idLists['fe_users']);
//        }
//
//        if (is_array($idLists[$userTable] ?? false) && $userTable) {
//            $idLists[$userTable] = array_unique($idLists[$userTable]);
//        }
//
//        if (is_array($idLists['tx_mail_domain_model_group'] ?? false)) {
//            $idLists['tx_mail_domain_model_group'] = RecipientUtility::removeDuplicates($idLists['tx_mail_domain_model_group']);
//        }

        return $idLists;
    }

    /**
     * collects all recipient uids from a given group respecting there categories
     *
     * @param Group $group Recipient group ID
     * @param string $userTable
     * @return array List of recipient IDs
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getRecipientsUidListsGroupedByTable(Group $group, string $userTable = ''): array
    {
        $idLists = [];
        switch ($group->getType()) {
            case RecipientGroupType::PAGES:
                // From pages
                $pages = $this->getRecursivePagesList($group->getPages(), $group->isRecursive());

                // Make queries
                if ($pages) {
                    if ($group->hasAddress()) {
                        $idLists['tt_address'] = $this->getRecipientUidListByTableAndPageUidListAndGroup('tt_address', $pages, $group);
                    }
                    if ($group->hasFrontendUser()) {
                        $idLists['fe_users'] = $this->getRecipientUidListByTableAndPageUidListAndGroup('fe_users', $pages, $group);
                    }
                    if ($group->hasFrontendUserGroup()) {
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'] ?? [], $this->getRecipientUidListByTableAndPageUidListAndGroup('fe_groups', $pages, $group)));
                    }
                    if ($userTable && $group->hasCustom()) {
                        $idLists[$userTable] = $this->getRecipientUidListByTableAndPageUidListAndGroup($userTable, $pages, $group);
                    }
                }
                break;
            case RecipientGroupType::MODEL:
                $pages = $this->getRecursivePagesList($group->getPages(), $group->isRecursive());
                if ($pages && $group->getRecordType()) {
                    $idLists[$group->getRecordType()] = $this->getRecipientUidListByModelNameAndStoragePageIdsAndCategories($group->getRecordType(), $pages, $group->getCategories());
                }
                break;
            case RecipientGroupType::CSV:
                // List of mails
                if ($group->isCsv()) {
                    $recipients = CsvUtility::rearrangeCsvValues($group->getList());
                } else {
                    $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $group->getList())));
                }
                $idLists['tx_mail_domain_model_group'] = RecipientUtility::removeDuplicates($recipients);
                break;
            case RecipientGroupType::STATIC:
                // Static MM list
                $idLists['tt_address'] = $this->getStaticIdListByTableAndGroupUid('tt_address', $group->getUid());
                $idLists['fe_users'] = $this->getStaticIdListByTableAndGroupUid('fe_users', $group->getUid());
                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $this->getStaticIdListByTableAndGroupUid('fe_groups', $group->getUid())));
                if ($userTable) {
                    $idLists[$userTable] = $this->getStaticIdListByTableAndGroupUid($userTable, $group->getUid());
                }
                break;
            case RecipientGroupType::QUERY:
                // Special query list
                // Todo add functionality again
                $queryTable = GeneralUtility::_GP('SET')['queryTable'] ?? '';
                $queryConfig = GeneralUtility::_GP('dmail_queryConfig');
                $this->updateGroupQueryConfig($group, $userTable, $queryTable, $queryConfig);

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
                $groups = $this->getRecursiveGroups($group);
                foreach ($groups as $recursiveGroup) {
                    $collect = $this->getRecipientsUidListsGroupedByTable($recursiveGroup, $userTable);
                    $idLists = array_merge_recursive($idLists, $collect);
                }
                break;
            default:
        }

        // todo add event dispatcher to manipulate the returned idLists

        return $idLists;
    }

    /**
     * @throws InvalidQueryException
     */
    protected function getRecipientUidListByModelNameAndStoragePageIdsAndCategories(string $modelName, array $storagePageIds, ObjectStorage $categories): array
    {
        $recipientUids = [];
//        $repositoryNameqq = ClassNamingUtility::translateModelNameToRepositoryName($modelName);
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $query = $persistenceManager->createQueryForType($modelName);
        if ($storagePageIds) {
            $query->getQuerySettings()->setStoragePageIds($storagePageIds);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(false);
        }
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setLanguageOverlayMode(false);
        $constrains = [
            $query->equals('active', true),
            $query->logicalNot($query->equals('email', ''))
        ];
        if ($categories->count() > 0) {
            $orCategoryConstrains = [];
            foreach ($categories as $category) {
                $orCategoryConstrains[] = $query->contains('categories', $category);
            }
            $constrains[] = $query->logicalOr(...$orCategoryConstrains);
        }
        $query->matching(
            $query->logicalAnd($constrains)
        );
        $debugResult = $this->debugQuery($query);
        $recipients = $query->execute();
        ViewUtility::addFlashMessageInfo($debugResult, 'Count ' . $recipients->count(), true);

        foreach ($recipients as $recipient) {
            if ($recipient instanceof RecipientInterface) {
                $recipientUids[] = $recipient->getUid();
            }
        }

        return $recipientUids;
    }

    /**
     * Return uid list from $table where the $pid is in $pages.
     * If no group categories set, all entries (within the given pages) will be returned,
     * otherwise only entries with matching categories will be returned.
     *
     * @param string $table source table
     * @param array $pages uid list of pages
     * @param Group $group mail group
     *
     * @return array The resulting array of uids
     * @throws Exception
     * @throws DBALException
     */
    protected function getRecipientUidListByTableAndPageUidListAndGroup(string $table, array $pages, Group $group): array
    {
        $switchTable = $table === 'fe_groups' ? 'fe_users' : $table;
        $queryBuilder = $this->getQueryBuilder($table);

        $addWhere = '';
        if ($switchTable === 'fe_users') {
            $addWhere = $queryBuilder->expr()->eq('fe_users.newsletter', 1);
        }

        if ($group->getCategories()->count() === 0) {
            // get recipients without category restriction
            if ($table === 'fe_groups') {
                return array_column($queryBuilder
                    ->selectLiteral('DISTINCT fe_users.uid', 'fe_users.email')
                    ->from('fe_users', 'fe_users')
                    ->from('fe_groups', 'fe_groups')
                    ->where(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY)))
                            ->add($queryBuilder->expr()->inSet('fe_users.usergroup', 'fe_groups.uid', true))
                            ->add($queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')))
                            ->add($queryBuilder->expr()->eq('fe_users.newsletter', 1))
                    )
                    ->orderBy('fe_users.uid')
                    ->addOrderBy('fe_users.email')
                    ->executeQuery()
                    ->fetchAllAssociative(), 'uid');
            } else {
                return array_column($queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($switchTable)
                    ->where(
                        $queryBuilder->expr()->and()
                            ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY)))
                            ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->executeQuery()
                    ->fetchAllAssociative(), 'uid');
            }
        } else {
            // get recipients with same categories set in group
            $recipients = [];
            $frontendUserGroups = $table === 'fe_groups' ? array_column(GeneralUtility::makeInstance(FrontendUserGroupRepository::class)->findRecordByPidList($pages, ['uid']), 'uid') : [];
            /** @var Category $category */
            foreach ($group->getCategories() as $category) {
                // collect all recipients containing at least one category of the given group
                $recipientCollection = CategoryCollection::load($category->getUid(), true, $switchTable, 'categories');
                foreach ($recipientCollection as $recipient) {
                    if (!in_array($recipient['uid'], $recipients) &&
                        in_array($recipient['pid'], $pages) &&
                        ($switchTable === 'tt_address' || $recipient['newsletter']) &&
                        ($table !== 'fe_groups' || count(array_intersect($frontendUserGroups, GeneralUtility::intExplode(',', $recipient['usergroup']))) > 0)
                    ) {
                        // add it to the list if all constrains fulfilled
                        $recipients[] = $recipient['uid'];
                    }
                }
            }
            return $recipients;
        }
    }

    /**
     * Construct the array of uids from $table selected
     * by special query of mail group of such type
     *
     * @param string $table The table to select from
     * @param Group $group
     *
     * @return array The resulting query.
     * @throws \Doctrine\DBAL\Exception|Exception
     */
    protected function getSpecialQueryIdList(string $table, Group $group): array
    {
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        if ($group->getQuery()) {
            $queryGenerator->init('dmail_queryConfig', $table);
            $queryGenerator->queryConfig = $queryGenerator->cleanUpQueryConfig(unserialize($group->getQuery()));

            $queryGenerator->extFieldLists['queryFields'] = 'uid';
            $select = $queryGenerator->getSelectQuery();
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

            return array_column($connection->executeQuery($select)->fetchAllAssociative(), 'uid');
        }
        return [];
    }

    /**
     * Return all uids from $table for a static mail group.
     *
     * @param string $table The table to select from
     * @param int $mailGroupUid The uid of the mail group
     *
     * @return array The resulting array of uids
     * @throws Exception
     * @throws DBALException
     */
    protected function getStaticIdListByTableAndGroupUid(string $table, int $mailGroupUid): array
    {
        $switchTable = $table === 'fe_groups' ? 'fe_users' : $table;

        $queryBuilder = $this->getQueryBuilder($table);

        $newsletterExpression = '';
        if ($switchTable === 'fe_users') {
            // for fe_users and fe_group, only activated newsletter
            $newsletterExpression = $queryBuilder->expr()->eq($switchTable . '.newsletter', 1);
        }

        if ($table === 'fe_groups') {
            $res = $queryBuilder
                ->selectLiteral('DISTINCT fe_users.uid', 'fe_users.email')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
                ->innerJoin(
                    'tx_mail_group_mm',
                    'tx_mail_domain_model_group',
                    'tx_mail_domain_model_group',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->innerJoin(
                    'tx_mail_group_mm',
                    'fe_groups',
                    'fe_groups',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier('fe_groups.uid'))
                )
                ->innerJoin(
                    'fe_groups',
                    'fe_users',
                    'fe_users',
                    $queryBuilder->expr()->inSet('fe_users.usergroup', $queryBuilder->quoteIdentifier('fe_groups.uid'), true)
                )
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                        ->add($queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)))
                        ->add($newsletterExpression)
                )
                ->orderBy('fe_users.uid')
                ->addOrderBy('fe_users.email')
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
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($switchTable)))
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)))
                        ->add($newsletterExpression)
                )
                ->orderBy($switchTable . '.uid')
                ->addOrderBy($switchTable . '.email')
                ->execute();
        }

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        if ($table === 'fe_groups') {
            // get the uid of the current fe_group
//            $queryBuilder = $this->getQueryBuilder('fe_groups');

            $res = $queryBuilder->resetQueryParts()
                ->selectLiteral('DISTINCT fe_groups.uid')
                ->from('fe_groups', 'fe_groups')
                ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
                ->leftJoin(
                    'tx_mail_domain_model_group',
                    'tx_mail_group_mm',
                    'tx_mail_group_mm',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('fe_groups.uid', $queryBuilder->quoteIdentifier('tx_mail_group_mm.uid_foreign')))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter('fe_groups')))
                )
                ->execute();

            @[$groupId] = $res->fetchAllAssociative();

            // recursively get all subgroups of this fe_group
            if (is_integer($groupId)) {
                $subgroups = $this->getRecursiveFrontendUserGroups($groupId);

                if ($subgroups) {
                    $subGroupExpressions = [];
                    foreach ($subgroups as $subgroup) {
                        $subGroupExpressions[] = $queryBuilder->expr()->inSet('fe_users.usergroup', $subgroup);
                    }

                    // fetch all fe_users from these subgroups
                    $res = $queryBuilder->resetQueryParts()
                        ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                        ->from($table, $table)
                        ->innerJoin(
                            $table,
                            $switchTable,
                            $switchTable
                        )
                        ->orWhere(...$subGroupExpressions)
                        ->andWhere(
                            $queryBuilder->expr()->and()
                                ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                                ->add($newsletterExpression)
                        )
                        ->orderBy($switchTable . '.uid')
                        ->addOrderBy($switchTable . '.email')
                        ->execute();

                    while ($row = $res->fetchAssociative()) {
                        $outArr[] = $row['uid'];
                    }
                }
            }
        }

        return array_unique($outArr);
    }


    /*
     * U P D A T E S
     */

    /**
     * @param Group $group
     * @param string $userTable
     * @param mixed $queryTable
     * @param mixed $queryConfig
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function updateGroupQueryConfig(Group $group, string $userTable, mixed $queryTable, mixed $queryConfig): void
    {
        $table = '';
        if ($group->hasAddress()) {
            $table = 'tt_address';
        } else {
            if ($group->hasFrontendUser()) {
                $table = 'fe_users';
            } else {
                if ($userTable && ($group->hasCustom())) {
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
    }

    /*
     * H E L P E R
     */

    /**
     * @param Group $group
     * @param int $recursion
     * @return Group[]
     */
    protected function getRecursiveGroups(Group $group, int $recursion = 0): array
    {
        $recursion++;
        if ($recursion > 20) {
            return [];
        }
        $groups = [];
        if ($group->getType() !== RecipientGroupType::OTHER) {
            $groups = [$group];
        }
        $childGroups = $group->getChildren();
        if ($group->getType() === RecipientGroupType::OTHER && $childGroups->count() > 0) {
            /** @var Group $childGroup */
            foreach ($childGroups as $childGroup) {
                $collect = $this->getRecursiveGroups($childGroup, $recursion);
                $groups = array_merge_recursive($groups, $collect);
            }
        }

        return $groups;
    }

    /**
     * @param string $pagesCSV
     * @param bool $recursive
     * @return array
     */
    protected function getRecursivePagesList(string $pagesCSV, bool $recursive): array
    {
        if (empty($pagesCSV)) {
            return [];
        }

        $pages = GeneralUtility::intExplode(',', $pagesCSV, true);

        if (!$recursive) {
            return $pages;
        }

        $pageIdArray = [];

        foreach ($pages as $pageUid) {
            if ($pageUid > 0) {
                $backendUserPermissions = BackendUserUtility::backendUserPermissions();
                $pageInfo = BackendUtility::readPageAccess($pageUid, $backendUserPermissions);
                if (is_array($pageInfo)) {
                    $pageIdArray[] = $pageUid;
                    // Finding tree and offer setting of values recursively.
                    $tree = GeneralUtility::makeInstance(PageTreeView::class);
                    $tree->init('AND ' . $backendUserPermissions);
                    $tree->makeHTML = 0;
                    $tree->setRecs = 0;
                    $tree->getTree($pageUid, 10000);
                    $pageIdArray = array_merge($pageIdArray, $tree->ids);
                }
            }
        }
        return array_unique($pageIdArray);
    }

    /**
     * Get all subgroups recursively.
     *
     * @param int $groupId Parent fe usergroup
     *
     * @return array The all id of fe_groups
     * @throws Exception
     * @throws DBALException
     */
    protected function getRecursiveFrontendUserGroups(int $groupId): array
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
            ->where(
                $queryBuilder->expr()->inSet('fe_groups.subgroup', (string)$groupId)
            )
            ->execute();
        $groupArr = [];

        while ($row = $res->fetchAssociative()) {
            $groupArr[] = $row['uid'];

            // add all subgroups recursively too
            $groupArr = array_merge($groupArr, $this->getRecursiveFrontendUserGroups($row['uid']));
        }

        return $groupArr;
    }

    /*
     * D A T A B A S E
     */

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string|null $table
     * @return QueryBuilder
     */
    protected function getQueryBuilder(string $table = null): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table);
    }

    /**
     * @param string|null $table
     * @param bool $withDeleted
     * @return QueryBuilder
     */
    protected function getQueryBuilderWithoutRestrictions(string $table = null, bool $withDeleted = false): QueryBuilder
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
