<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Database\QueryGenerator;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\DebugQueryTrait;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Category\Collection\CategoryCollection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class RecipientService
{
    use DebugQueryTrait;

    protected array $allowedTables = ['fe_users', 'tt_address'];
    protected array $recipientSources = [];

    public function __construct(
        protected GroupRepository $groupRepository,
        protected AddressRepository $addressRepository,
        protected FrontendUserRepository $frontendUserRepository,
        protected EventDispatcherInterface $eventDispatcher,
        protected PersistenceManager $persistenceManager
    ) {
    }

    public function init(array $recipientSources): void
    {
        $this->recipientSources = $recipientSources;
    }

    /**
     * @param int $pageId
     * @return array
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFinalSendingGroups(int $pageId): array
    {
        $mailGroups = [];
        $groups = $this->groupRepository->findByPid($pageId);
        if ($groups->count() > 0) {
            /** @var Group $group */
            foreach ($groups as $group) {
                $mailGroups[] = [
                    'uid' => $group->getUid(),
                    'title' => $group->getTitle(),
                    'receiver' => $this->getNumberOfRecipientsByGroup($group),
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
     * @param bool $categoriesAsList
     *
     * @return array recipients' data
     * @throws Exception
     */
    public function getRecipientsDataByUidListAndTable(
        array $uidListOfRecipients,
        string $table,
        array $fields = ['uid', 'name', 'email', 'categories', 'mail_html'],
        bool $categoriesAsList = false
    ): array {
        if (!$uidListOfRecipients) {
            return [];
        }
        $data = [];
        //$queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions($table);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $res = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uidListOfRecipients, Connection::PARAM_INT_ARRAY)))
            ->executeQuery();

        $replaceCategories = in_array('categories', $fields);
        while ($row = $res->fetchAssociative()) {
            if ($replaceCategories) {
                if ($row['categories'] ?? false) {
                    $items = $this->getCategoriesOfRecipient($row['uid'], $table);
                    if ($items) {
                        if ($categoriesAsList) {
                            $row['categories'] = implode(', ', array_column($items, 'title'));
                        } else {
                            $row['categories'] = $items;
                        }
                    } else {
                        $row['categories'] = $categoriesAsList ? '' : [];
                    }
                } else {
                    $row['categories'] = $categoriesAsList ? '' : [];
                }
            }
            $data[$row['uid']] = $row;
        }

        return $data;
    }

    /**
     * Get recipient DB record given on the ID
     *
     * @param array $uidListOfRecipients List of recipient IDs
     * @param string $modelName model name
     * @param array $fields Field to be selected. If empty enhanced model data will be returned
     * @param bool $withCategoryUidsArray
     * @param int $limit limit of results
     *
     * @return array recipients' data
     * @throws InvalidQueryException
     */
    public function getRecipientsDataByUidListAndModelName(
        array $uidListOfRecipients,
        string $modelName,
        array $fields = ['uid', 'name', 'email', 'categories', 'mail_html'],
        bool $withCategoryUidsArray = false,
        int $limit = 0
    ): array {
        if (!$uidListOfRecipients || !$modelName) {
            return [];
        }
        $data = [];
        $query = $this->persistenceManager->createQueryForType($modelName);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setLanguageOverlayMode(false);
        $query->matching(
            $query->in('uid', $uidListOfRecipients)
        );
        if ($limit > 0) {
            $query->setLimit($limit);
        }
//        $debugResult = $this->debugQuery($query);
        $recipients = $query->execute();
//        ViewUtility::addFlashMessageInfo($debugResult, 'Count ' . $recipients->count(), true);

        $firstRecipient = $recipients->getFirst();
        if (!$firstRecipient instanceof RecipientInterface && !$firstRecipient instanceof DomainObjectInterface) {
            return [];
        }

        $getters = [];
        $hasFields = !empty($fields);
        if ($hasFields) {
            foreach ($fields as $field) {
                $propertyName = ucfirst(str_contains($field, '_') ? str_replace(' ', '', ucwords(str_replace('_', ' ', $field))) : $field);
                if (method_exists($firstRecipient, 'get' . $propertyName)) {
                    $getters[$field] = 'get' . $propertyName;
                } else {
                    if (method_exists($firstRecipient, 'is' . $propertyName)) {
                        $getters[$field] = 'is' . $propertyName;
                    }
                }
            }
        }

        /** @var RecipientInterface $recipient */
        foreach ($recipients as $recipient) {
            $data[$recipient->getUid()] = $hasFields ? RecipientUtility::getFlatRecipientModelData($recipient, $getters,
                $withCategoryUidsArray) : $recipient->getEnhancedData();
        }

        return $data;
    }

    /**
     * @param ObjectStorage<Group> $groups
     * @return array
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getRecipientsUidListsGroupedByRecipientSource(ObjectStorage $groups): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if ($groups->count() === 0) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            $recipientList = $this->getRecipientsUidListGroupedByRecipientSource($group, true);
            $idLists = array_merge_recursive($idLists, $recipientList);
        }

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            if (str_starts_with($recipientSourceIdentifier, 'tx_mail_domain_model_group')) {
                $idLists[$recipientSourceIdentifier] = RecipientUtility::removeDuplicates($idList);
            } else {
                $idLists[$recipientSourceIdentifier] = array_unique($idList);
            }
        }

        return $idLists;
    }

    /**
     * @throws UnknownObjectException
     * @throws InvalidQueryException
     * @throws IllegalObjectTypeException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @deprecated
     */
    public function getNumberOfRecipientsByGroups(ObjectStorage $recipientGroups): int
    {
        $numberOfRecipients = 0;
        foreach ($recipientGroups as $recipientGroup) {
            $numberOfRecipients += $this->getNumberOfRecipientsByGroup($recipientGroup);
        }

        return $numberOfRecipients;
    }

    /**
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getNumberOfRecipientsByGroup(Group $group): int
    {
        return RecipientUtility::calculateTotalRecipientsOfUidLists($this->getRecipientsUidListGroupedByRecipientSource($group));
    }

    /**
     * collects all recipient uids from a given group respecting there categories
     *
     * @param Group $group Recipient group ID
     * @param bool $addGroupUidToRecipientSourceIdentifier
     * @return array List of recipient IDs
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws DBALException
     */
    public function getRecipientsUidListGroupedByRecipientSource(Group $group, bool $addGroupUidToRecipientSourceIdentifier = false): array
    {
        $idLists = [];
        switch ($group->getType()) {
            case RecipientGroupType::PAGES:
                // From pages
                $pages = BackendDataUtility::getRecursivePagesList($group->getPages(), $group->isRecursive());
                if ($pages) {
                    foreach ($group->getRecipientSources() as $recipientSourceIdentifier) {
                        $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
                        if ($recipientSourceConfiguration) {
                            $recipientSourceIdentifier = $recipientSourceConfiguration['contains'] ?? $recipientSourceIdentifier;
                            $ignoreMailActive = $recipientSourceConfiguration['ignoreMailActive'] ?? false;
                            if ($recipientSourceConfiguration['model'] ?? false) {
                                $idLists[$recipientSourceIdentifier] = $this->getRecipientUidListByModelNameAndPageUidListAndCategories(
                                    $recipientSourceConfiguration['model'],
                                    $pages,
                                    $group->getCategories(),
                                    $ignoreMailActive
                                );
                            } else {
                                $idLists[$recipientSourceIdentifier] = $this->getRecipientUidListByTableAndPageUidListAndCategories(
                                    $recipientSourceIdentifier,
                                    $pages,
                                    $group->getCategories(),
                                    $ignoreMailActive
                                );
                            }
                        }
                    }
                }
                break;
            case RecipientGroupType::CSV:
                // List of mails
                if ($group->isCsv()) {
                    $recipients = CsvUtility::rearrangeCsvValues($group->getList());
                } else {
                    $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $group->getList())));
                }
                foreach ($recipients as $key => $recipient) {
                    $recipients[$key]['categories'] = $group->getCategories();
                    $recipients[$key]['mail_html'] = $group->isMailHtml() ? 1 : 0;
                }
                $csvRecipientSourceIdentifier = $addGroupUidToRecipientSourceIdentifier ? 'tx_mail_domain_model_group:' . $group->getUid() : 'tx_mail_domain_model_group';
                $idLists[$csvRecipientSourceIdentifier] = RecipientUtility::removeDuplicates($recipients);
                break;
            case RecipientGroupType::STATIC:
                // Static MM list
                foreach ($this->recipientSources as $recipientSourceIdentifier => $recipientSourceConfiguration) {
                    $ignoreMailActive = $recipientSourceConfiguration['ignoreMailActive'] ?? false;
                    $idListKey = $recipientSourceConfiguration['contains'] ?? $recipientSourceIdentifier;
                    $idLists[$idListKey] = array_unique(array_merge($idLists[$idListKey] ?? [],
                        $this->getStaticIdListByTableAndGroupUid($recipientSourceIdentifier, $group->getUid(), $ignoreMailActive)));
                }
                break;
//            case RecipientGroupType::QUERY:
//                // Special query list
//                // Todo add functionality again
//                $queryTable = GeneralUtility::_GP('SET')['queryTable'] ?? '';
//                $queryConfig = GeneralUtility::_GP('mailQueryConfig');
//                $this->updateGroupQueryConfig($group, $queryTable, $queryConfig);
//
//                $table = '';
//                if ($group->hasAddress()) {
//                    $table = 'tt_address';
//                } else {
//                    if ($group->hasFrontendUser()) {
//                        $table = 'fe_users';
//                    }
//                }
//                if ($table) {
//                    $idLists[$table] = $this->getSpecialQueryIdList($table, $group);
//                }
//                break;
            case RecipientGroupType::OTHER:
                $childGroups = $group->getChildren();
                foreach ($childGroups as $childGroup) {
                    $collect = $this->getRecipientsUidListGroupedByRecipientSource($childGroup, $addGroupUidToRecipientSourceIdentifier);
                    $idLists = array_merge_recursive($idLists, $collect);
                }
                break;
        }

        foreach ($idLists as $recipientSource => $idList) {
            $idLists[$recipientSource] = str_starts_with($recipientSource, 'tx_mail_domain_model_group') ? $idList : array_unique($idList);
        }

        // todo add event dispatcher to manipulate the returned idLists

        return $idLists;
    }

    /**
     * @throws InvalidQueryException
     */
    protected function getRecipientUidListByModelNameAndPageUidListAndCategories(
        string $modelName,
        array $storagePageIds,
        ObjectStorage $categories,
        bool $ignoreMailActive = false
    ): array {
        $recipientUids = [];
        $query = $this->persistenceManager->createQueryForType($modelName);
        if ($storagePageIds) {
            $query->getQuerySettings()->setStoragePageIds($storagePageIds);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(false);
        }
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setLanguageOverlayMode(false);
        $constrains = [];

        if (!$ignoreMailActive) {
            $constrains[] = $query->equals('active', true);
        }

        $constrains[] = $query->logicalNot($query->equals('email', ''));

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
//        $debugResult = $this->debugQuery($query);
        $recipients = $query->execute();
//        ViewUtility::addFlashMessageInfo($debugResult, 'Count ' . $recipients->count(), true);

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
     * @param ObjectStorage<Category> $categories mail categories
     *
     * @return array The resulting array of uids
     * @throws Exception
     * @throws DBALException
     */
    protected function getRecipientUidListByTableAndPageUidListAndCategories(
        string $table,
        array $pages,
        ObjectStorage $categories,
        bool $ignoreMailActive = false
    ): array {
        $switchTable = $table === 'fe_groups' ? 'fe_users' : $table;
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $mailActiveExpression = '';
        if (!$ignoreMailActive) {
            $mailActiveExpression = $queryBuilder->expr()->eq($switchTable . '.mail_active', 1);
        }

        if ($categories->count() === 0) {
            // get recipients without category restriction
            if ($table === 'fe_groups') {
                return array_column($queryBuilder
                    ->select('fe_users.uid')
                    ->from('fe_users', 'fe_users')
                    ->from('fe_groups', 'fe_groups')
                    ->where(
                        $queryBuilder->expr()->and()
                            ->add($mailActiveExpression)
                            ->add($queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')))
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY)))
                            ->add($queryBuilder->expr()->inSet('fe_users.usergroup', 'fe_groups.uid', true))
                    )
                    ->orderBy('fe_users.uid')
                    ->addOrderBy('fe_users.email')
                    ->executeQuery()
                    ->fetchAllAssociative(), 'uid');
            }

            return array_column($queryBuilder
                ->select($switchTable . '.uid')
                ->from($switchTable)
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($mailActiveExpression)
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY)))
                )
                ->orderBy($switchTable . '.uid')
                ->executeQuery()
                ->fetchAllAssociative(), 'uid');
        }

        // get recipients with same categories set in group
        $recipients = [];
        $frontendUserGroups = $table === 'fe_groups' ? array_column(GeneralUtility::makeInstance(FrontendUserGroupRepository::class)->findRecordByPidList($pages,
            ['uid']), 'uid') : [];
        $disabledField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] ?? false;
        /** @var Category $category */
        foreach ($categories as $category) {
            // collect all recipients containing at least one category of the given group
            $recipientCollection = CategoryCollection::load($category->getUid(), true, $switchTable, 'categories');
            foreach ($recipientCollection as $recipient) {
                if ((!$disabledField || !$recipient[$disabledField]) &&
                    ($ignoreMailActive || $recipient['mail_active'] ?? true) &&
                    !in_array($recipient['uid'], $recipients) &&
                    in_array($recipient['pid'], $pages) &&
                    ($table !== 'fe_groups' || count(array_intersect($frontendUserGroups, GeneralUtility::intExplode(',', $recipient['usergroup']))) > 0)
                ) {
                    // add it to the list if all constrains fulfilled
                    $recipients[] = $recipient['uid'];
                }
            }
        }
        return $recipients;
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
            $queryGenerator->init('mail_queryConfig', $table);
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
    protected function getStaticIdListByTableAndGroupUid(string $table, int $mailGroupUid, bool $ignoreMailActive = false): array
    {
        $switchTable = $table === 'fe_groups' ? 'fe_users' : $table;

        $queryBuilder = $this->getQueryBuilder($table);

        $mailActiveExpression = '';
        if (!$ignoreMailActive) {
            // for fe_users and fe_group, only activated newsletter
            $mailActiveExpression = $queryBuilder->expr()->eq($switchTable . '.mail_active', 1);
        }

        if ($table === 'fe_groups') {
            $idList = array_column($queryBuilder
                ->select('fe_users.uid')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
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
                        ->add($mailActiveExpression)
                        ->add($queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                )
                ->orderBy('fe_users.uid')
                ->executeQuery()
                ->fetchAllAssociative(), 'uid');
        } else {
            $idList = array_column($queryBuilder
                ->select($switchTable . '.uid')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
                ->innerJoin(
                    'tx_mail_group_mm',
                    $switchTable,
                    $switchTable,
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier($switchTable . '.uid'))
                )
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($mailActiveExpression)
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter($switchTable)))
                )
                ->orderBy($switchTable . '.uid')
                ->executeQuery()
                ->fetchAllAssociative(), 'uid');
        }

        if ($table === 'fe_groups') {
            // get the uid of the current fe_group
            $frontendUserGroups = array_column($queryBuilder->resetQueryParts()
                ->select('fe_groups.uid')
                ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
                ->leftJoin(
                    'tx_mail_domain_model_group',
                    'tx_mail_group_mm',
                    'tx_mail_group_mm',
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local', $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
                )
                ->leftJoin(
                    'tx_mail_group_mm',
                    'fe_groups',
                    'fe_groups',
                    $queryBuilder->expr()->eq('fe_groups.uid', $queryBuilder->quoteIdentifier('tx_mail_group_mm.uid_foreign'))
                )
                ->where(
                    $queryBuilder->expr()->and()
                        ->add($queryBuilder->expr()->eq('tx_mail_domain_model_group.uid', $queryBuilder->createNamedParameter($mailGroupUid, PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('tx_mail_group_mm.tablenames', $queryBuilder->createNamedParameter('fe_groups')))
                )
                ->executeQuery()
                ->fetchAllAssociative(), 'uid');

            if ($frontendUserGroups) {
                foreach ($frontendUserGroups as $frontendUserGroup) {
                    // recursively get all subgroups of this fe_groups
                    $subgroups = $this->getRecursiveFrontendUserGroups($frontendUserGroup);

                    if ($subgroups) {
                        $subGroupExpressions = [];
                        foreach ($subgroups as $subgroup) {
                            $subGroupExpressions[] = $queryBuilder->expr()->inSet('fe_users.usergroup', (string)$subgroup);
                        }

                        // fetch all fe_users from these subgroups
                        $idList = array_merge($idList, array_column($queryBuilder->resetQueryParts()
                            ->select('fe_users.uid')
                            ->from('fe_groups', 'fe_groups')
                            ->innerJoin(
                                'fe_groups',
                                'fe_users',
                                'fe_users'
                            )
                            ->orWhere(...$subGroupExpressions)
                            ->andWhere(
                                $queryBuilder->expr()->and()
                                    ->add($mailActiveExpression)
                                    ->add($queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')))
                            )
                            ->orderBy('fe_users.uid')
                            ->executeQuery()
                            ->fetchAllAssociative(), 'uid'));
                    }
                }
            }
        }

        return array_unique($idList);
    }


    /*
     * U P D A T E S
     */

    /**
     * @param Group $group
     * @param mixed $queryTable
     * @param mixed $queryConfig
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * todo
     */
    protected function updateGroupQueryConfig(Group $group, mixed $queryTable, mixed $queryConfig): void
    {
        $table = '';
        if ($group->hasAddress()) {
            $table = 'tt_address';
        } else {
            if ($group->hasFrontendUser()) {
                $table = 'fe_users';
            }
        }

        $queryTable = $queryTable ?: $table;
        $queryConfig = $queryConfig ? serialize($queryConfig) : $group->getQuery();

        if ($queryTable != $table) {
            $queryConfig = '';
        }

        if ($queryTable != $table || $queryConfig != $group->getQuery()) {
            $recordTypes = [];
            if ($queryTable === 'tt_address') {
                $recordTypes = 'tt_address';
            } else {
                if ($queryTable === 'fe_users') {
                    $recordTypes = 'fe_users';
                }
            }

            $group->setrecipientSources($recordTypes);
            $group->setQuery($queryConfig);
            $this->groupRepository->update($group);
            $this->groupRepository->persist();
        }
    }

    /**
     * @param array $data
     * @return int
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function disableRecipients(array $data): int
    {
        return $this->eventDispatcher->dispatch(new DeactivateRecipientsEvent($data, $this->recipientSources))->getNumberOfAffectedRecipients();
    }


    /*
     * H E L P E R
     */

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

        $res = $queryBuilder
            ->select('fe_groups.uid')
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
            ->executeQuery();
        $groupArr = [];

        while ($row = $res->fetchAssociative()) {
            $groupArr[] = $row['uid'];

            // add all subgroups recursively too
            $groupArr = array_merge($groupArr, $this->getRecursiveFrontendUserGroups($row['uid']));
        }

        return array_unique($groupArr);
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function getCategoriesOfRecipient(int $uid, string $table, $categoryFieldName = 'categories'): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions('sys_category_record_mm');
        return $queryBuilder->select('c.title')
            ->from('sys_category_record_mm', 'mm')
            ->leftJoin(
                'mm',
                'sys_category',
                'c',
                $queryBuilder->expr()->eq('c.uid', $queryBuilder->quoteIdentifier('mm.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->and()
                    ->add($queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
                    ->add($queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter($table)))
                    ->add($queryBuilder->expr()->eq('mm.fieldname', $queryBuilder->createNamedParameter($categoryFieldName)))
            )
            ->executeQuery()
            ->fetchAllAssociative();
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
