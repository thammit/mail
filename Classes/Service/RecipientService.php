<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\DebugQueryTrait;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use MEDIAESSENZ\Mail\Events\RecipientsRestrictionEvent;
use MEDIAESSENZ\Mail\Type\Enumeration\CategoryFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Category\Collection\CategoryCollection;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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

    protected array $recipientSources = [];
    private bool $ttAddressIsLoaded;

    public function __construct(
        protected GroupRepository $groupRepository,
        protected EventDispatcherInterface $eventDispatcher,
        protected PersistenceManager $persistenceManager
    ) {
        $this->ttAddressIsLoaded = ExtensionManagementUtility::isLoaded('tt_address');
    }

    public function init(array $recipientSources): void
    {
        $this->recipientSources = $recipientSources;
    }

    /**
     * @param int $pageId
     * @return array
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
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
     * @throws InvalidQueryException
     * @throws IllegalObjectTypeException
     * @throws Exception
     * @throws UnknownObjectException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getRecipientsByGroup(Group $group): array
    {
        $recipientSources = [];
        $recipientsUidListGroupedByRecipientSource = $this->getRecipientsUidListGroupedByRecipientSource($group);

        foreach ($recipientsUidListGroupedByRecipientSource as $recipientSourceIdentifier => $recipientUidList) {

            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;

            if (!$recipientUidList || !$recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                // skip empty id lists and lists without recipient source configuration
                continue;
            }

            $recipients = [];

            switch (true) {
                case $recipientSourceConfiguration->isTableSource():
                    $recipients = $this->getRecipientsDataByUidListAndTable($recipientUidList, $recipientSourceConfiguration->contains ?? $recipientSourceConfiguration->table);
                    if ($recipientSourceConfiguration->contains) {
                        $recipientSourceConfiguration->title = $recipientSourceConfiguration->containsTitle ?? $recipientSourceConfiguration->contains;
                    }
                    break;
                case $recipientSourceConfiguration->isModelSource():
                    $recipients = $this->getRecipientsDataByUidListAndModelName($recipientUidList, $recipientSourceConfiguration->model);
                    break;
                case $recipientSourceConfiguration->isCsvOrPlain():
                    $recipients = $recipientUidList;
                    break;
                case $recipientSourceConfiguration->isService():
                    // todo
                    break;
            }

            $table = $recipientSourceConfiguration->contains ?? $recipientSourceConfiguration->table;

            $recipientSources[$recipientSourceConfiguration->identifier] = [
                'configuration' => $recipientSourceConfiguration,
                'recipients' => $recipients,
                'numberOfRecipients' => count($recipients),
                'show' => $table && BackendUserUtility::getBackendUser()->check('tables_select', $table),
                'edit' => $table && BackendUserUtility::getBackendUser()->check('tables_modify', $table),
            ];
        }

        return $recipientSources;
    }

    /**
     * Get recipient DB record given on the ID
     *
     * @param array $uidListOfRecipients List of recipient IDs
     * @param string $table Table name
     * @param array $fields Field to be selected
     * @param int $categoryFormat
     *
     * @return array recipients' data
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidQueryException
     */
    public function getRecipientsDataByUidListAndTable(
        array $uidListOfRecipients,
        string $table,
        array $fields = ['uid', 'name', 'email', 'categories', 'mail_html', 'mail_active'],
        int $categoryFormat = CategoryFormat::OBJECTS
    ): array {
        if (!$uidListOfRecipients) {
            return [];
        }

        if (!in_array('uid', $fields)) {
            // we need the uid
            $fields[] = 'uid';
        }

        if (!in_array('email', $fields)) {
            // we need the email
            $fields[] = 'email';
        }

        $data = [];
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions($table);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $res = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where($queryBuilder->expr()->in('uid',
                $queryBuilder->createNamedParameter($uidListOfRecipients, Connection::PARAM_INT_ARRAY)))
            ->executeQuery();

        $replaceCategories = in_array('categories', $fields);
        while ($row = $res->fetchAssociative()) {
            if ($replaceCategories) {
                $categories = $categoryFormat === CategoryFormat::CSV ? '' : [];
                if ($row['categories'] ?? false) {
                    switch (true) {
                        case $categoryFormat === CategoryFormat::OBJECTS:
                            $categories = RecipientUtility::getObjectsOfRecipientCategories($table, $row['uid']);
                            break;
                        case $categoryFormat === CategoryFormat::UIDS:
                            $categories = RecipientUtility::getListOfRecipientCategories($table, $row['uid']);
                            break;
                        case $categoryFormat === CategoryFormat::CSV:
                            $categories = RecipientUtility::getCsvOfRecipientCategories($table, $row['uid']);
                            break;
                    }
                }
                $row['categories'] = $categories;

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
     * @param int $categoryFormat
     * @param int $limit limit of results
     *
     * @return array recipients' data
     * @throws InvalidQueryException
     */
    public function getRecipientsDataByUidListAndModelName(
        array $uidListOfRecipients,
        string $modelName,
        array $fields = ['uid', 'name', 'email', 'categories', 'mail_html', 'mail_active'],
        int $categoryFormat = CategoryFormat::OBJECTS,
        int $limit = 0
    ): array {
        if (!$uidListOfRecipients || !$modelName || !class_exists($modelName) || !is_subclass_of($modelName, RecipientInterface::class)) {
            return [];
        }

        if (!in_array('uid', $fields)) {
            // we need the uid
            $fields[] = 'uid';
        }

        if (!in_array('email', $fields)) {
            // we need the email
            $fields[] = 'email';
        }

        $data = [];
        $query = $this->persistenceManager->createQueryForType($modelName);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $query->getQuerySettings()->setLanguageOverlayMode();
        } else {
            $languageAspect = $query->getQuerySettings()->getLanguageAspect();
            if ($languageAspect->getOverlayType() !== LanguageAspect::OVERLAYS_OFF) {
                $query->getQuerySettings()->setLanguageAspect(new LanguageAspect($languageAspect->getId(),
                    $languageAspect->getContentId(),
                    LanguageAspect::OVERLAYS_OFF));
            }
        }

        $query->matching(
            $query->in('uid', $uidListOfRecipients)
        );

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        $recipients = $query->execute();
        $firstRecipient = $recipients->getFirst();

        if (!$firstRecipient instanceof RecipientInterface && !$firstRecipient instanceof DomainObjectInterface) {
            return [];
        }

        $getters = [];
        foreach ($fields as $field) {
            $propertyName = ucfirst(str_contains($field, '_') ? str_replace(' ', '',
                ucwords(str_replace('_', ' ', $field))) : $field);
            if (method_exists($firstRecipient, 'get' . $propertyName)) {
                $getters[$field] = 'get' . $propertyName;
            } else {
                if (method_exists($firstRecipient, 'is' . $propertyName)) {
                    $getters[$field] = 'is' . $propertyName;
                }
            }
        }

        /** @var RecipientInterface $recipient */
        foreach ($recipients as $recipient) {
            $data[$recipient->getUid()] = RecipientUtility::getFlatRecipientModelData($recipient, $getters, $categoryFormat);
        }

        return $data;
    }

    /**
     * @param ObjectStorage<Group> $groups
     * @return array
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
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
            $recipients = $this->getRecipientsUidListGroupedByRecipientSource($group, true);
            $idLists = array_merge_recursive($idLists, $recipients);
        }

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
            if ($recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                if ($recipientSourceConfiguration->isCsvOrPlain()) {
                    $idLists[$recipientSourceIdentifier] = RecipientUtility::removeDuplicates($idList);
                } else {
                    $idLists[$recipientSourceIdentifier] = array_unique($idList);
                }
            }
        }

        return $idLists;
    }

    /**
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEmailAddressesByRecipientsUidListGroupedByRecipientSource(array $recipientsUidListGroupedByRecipientSource): array
    {
        $emailAddresses = [];

        foreach ($recipientsUidListGroupedByRecipientSource as $recipientSourceIdentifier => $recipients) {
            $emailAddresses += $this->getEmailAddressesByRecipientSourceAndRecipientsList($recipientSourceIdentifier, $recipients);
        }

        return array_unique($emailAddresses);
    }

    /**
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEmailAddressesByRecipientSourceAndRecipientsList(string $recipientSourceIdentifier, array $recipients): array
    {
        /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
        $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;

        if (!$recipients || !$recipientSourceConfiguration) {
            return [];
        }

        $emails = [];

        switch (true) {
            case $recipientSourceConfiguration->isTableSource():
                $recipients = $this->getRecipientsDataByUidListAndTable($recipients, $recipientSourceConfiguration->contains ?? $recipientSourceConfiguration->table, ['uid', 'email']);
                break;
            case $recipientSourceConfiguration->isModelSource():
                $recipients = $this->getRecipientsDataByUidListAndModelName($recipients, $recipientSourceConfiguration->model, ['uid', 'email']);
                break;
            case $recipientSourceConfiguration->isCsvOrPlain():
                // nothing to do, since email is already in recipient array
                break;
            case $recipientSourceConfiguration->isService():
                // todo
                break;
        }

        foreach ($recipients as $recipient) {
            if ($recipient['email'] ?? false) {
                $emails[] = $recipient['email'];
            }
        }

        return $emails;
    }

    /**
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Exception
     */
    public function removeFromRecipientListIfInExcludeEmailsList(array $recipientsUidListGroupedByRecipientSource, array $excludeEmails): array
    {
        foreach ($recipientsUidListGroupedByRecipientSource as $recipientSourceIdentifier => $recipients) {
            /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
            if (!$recipientSourceConfiguration) {
                continue;
            }

            switch (true) {
                case $recipientSourceConfiguration->isTableSource():
                case $recipientSourceConfiguration->isModelSource():
                    if ($recipientSourceConfiguration->isTableSource()) {
                        $recipientsData = $this->getRecipientsDataByUidListAndTable($recipients, $recipientSourceConfiguration->contains ?? $recipientSourceConfiguration->table, ['uid', 'email']);
                    } else {
                        $recipientsData = $this->getRecipientsDataByUidListAndModelName($recipients, $recipientSourceConfiguration->model, ['uid', 'email']);
                    }
                    foreach ($recipientsData as $recipientData) {
                        $email = $recipientData['email'] ?? false;
                        if (in_array($email, $excludeEmails, true)) {
                            $recipientsUidListGroupedByRecipientSource[$recipientSourceIdentifier] = array_values(array_filter($recipients, static fn($uid) => $uid !== (int)$recipientData['uid']));
                        }
                    }
                    break;
                case $recipientSourceConfiguration->isCsvOrPlain():
                    // handle csv and plain lists
                    $recipientsToKeep = [];
                    foreach ($recipients as $recipient) {
                        $email = $recipient['email'] ?? false;
                        if (!in_array($email, $excludeEmails, true)) {
                            $recipientsToKeep[] = $recipient;
                        }
                    }
                    $recipientsUidListGroupedByRecipientSource[$recipientSourceIdentifier] = $recipientsToKeep;
                    break;
                case $recipientSourceConfiguration->isService():
                    // todo
                    break;
            }
        }

        return $recipientsUidListGroupedByRecipientSource;
    }

    /**
     * @param Group $group
     * @return int
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getNumberOfRecipientsByGroup(Group $group): int
    {
        $list = $this->getRecipientsUidListGroupedByRecipientSource($group);
        return RecipientUtility::calculateTotalRecipientsOfUidLists($list);
    }

    /**
     * collects all recipient uids from a given group respecting there categories
     *
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getRecipientsUidListGroupedByRecipientSource(Group $group, bool $categoriesAsUidList = false): array
    {
        $idLists = [];
        switch (true) {
            case $group->isPages():
                // From pages
                $pages = BackendDataUtility::getRecursivePagesList($group->getPages(), $group->isRecursive());
                if ($pages) {
                    foreach ($group->getRecipientSources() as $recipientSourceIdentifier) {
                        $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier];
                        if (!$recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                            continue;
                        }
                        if ($recipientSourceConfiguration->isModelSource()) {
                            $idLists[$recipientSourceIdentifier] = $this->getRecipientUidListByModelNameAndPageUidListAndCategories(
                                $recipientSourceConfiguration,
                                $pages,
                                $group->getCategories()
                            );
                        } else {
                            $idLists[$recipientSourceIdentifier] = $this->getRecipientUidListByTableAndPageUidListAndCategories(
                                $recipientSourceConfiguration,
                                $pages,
                                $group->getCategories()
                            );
                        }
                    }
                }
                break;
            case $group->isPlain():
            case $group->isCsv():
                $recipients = $group->isPlain() ? $group->getListRecipientsWithName() : $group->getCsvRecipients();
                foreach ($recipients as $key => $recipient) {
                    $recipients[$key]['categories'] = $categoriesAsUidList ? $group->getCategoriesUidList() : $group->getCategories();
                    $recipients[$key]['mail_html'] = $group->isMailHtml() ? 1 : 0;
                }
                $recipientSourceIdentifier = 'tx_mail_domain_model_group:' . $group->getUid();
                $idLists[$recipientSourceIdentifier] = RecipientUtility::removeDuplicates($recipients);
                break;
            case $group->isStatic():
                // Static MM list
                foreach ($this->recipientSources as $recipientSourceIdentifier => $recipientSourceConfiguration) {
                    if ($recipientSourceConfiguration->table === $recipientSourceConfiguration->identifier) {
                        // only add recipients sources where table name is identical to identifier
                        /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
                        $recipientSourceIdentifier = $recipientSourceConfiguration->contains ?? $recipientSourceIdentifier;
                        $idLists[$recipientSourceIdentifier] = array_unique(array_merge($idLists[$recipientSourceIdentifier] ?? [],
                            $this->getStaticIdListByTableAndGroupUid($recipientSourceConfiguration, $group->getUid())));
                    }
                }
                break;
            case $group->isOther():
                $childGroups = $group->getChildren();
                foreach ($childGroups as $childGroup) {
                    $collect = $this->getRecipientsUidListGroupedByRecipientSource($childGroup, $categoriesAsUidList);
                    $idLists = array_merge_recursive($idLists, $collect);
                }
                break;
        }

        $uniqueIdLists = [];

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
            if ($recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO) {
                $uniqueIdLists[$recipientSourceIdentifier] = $recipientSourceConfiguration->isCsvOrPlain() ? $idList : array_unique($idList);
            }
        }

        // todo add event dispatcher to manipulate the returned idLists

        return $uniqueIdLists;
    }

    /**
     * @throws InvalidQueryException
     */
    protected function getRecipientUidListByModelNameAndPageUidListAndCategories(
        RecipientSourceConfigurationDTO $recipientSourceConfiguration,
        array $storagePageIds,
        ObjectStorage $categories,
    ): array {
        $recipientUids = [];
        $query = $this->persistenceManager->createQueryForType($recipientSourceConfiguration->model);
        if ($storagePageIds) {
            $query->getQuerySettings()->setStoragePageIds($storagePageIds);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(false);
        }
        $query->getQuerySettings()->setRespectSysLanguage(false);
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $query->getQuerySettings()->setLanguageOverlayMode();
        } else {
            $languageAspect = $query->getQuerySettings()->getLanguageAspect();
            if ($languageAspect->getOverlayType() !== LanguageAspect::OVERLAYS_OFF) {
                $query->getQuerySettings()->setLanguageAspect(new LanguageAspect($languageAspect->getId(),
                    $languageAspect->getContentId(),
                    LanguageAspect::OVERLAYS_OFF));
            }
        }

        $constraints = [];

        if (!$recipientSourceConfiguration->ignoreMailActive) {
            $constraints[] = $query->equals('active', true);
        }

        $constraints[] = $query->logicalNot($query->equals('email', ''));

        if ($categories->count() > 0) {
            $orCategoryConstrains = [];
            foreach ($categories as $category) {
                $orCategoryConstrains[] = $query->contains('categories', $category);
            }
            $constraints[] = $query->logicalOr(...$orCategoryConstrains);
        }

        // PSR-14 event dispatcher to add custom query restrictions
        $constraints = $this->eventDispatcher->dispatch(new RecipientsRestrictionEvent($recipientSourceConfiguration,
            $query, $constraints))->getConstraints();

        $query->matching(
            $query->logicalAnd(...$constraints)
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
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getRecipientUidListByTableAndPageUidListAndCategories(
        RecipientSourceConfigurationDTO $recipientSourceConfiguration,
        array $pages,
        ObjectStorage $categories,
    ): array {
        $table = $recipientSourceConfiguration->table;
        $switchTable = $recipientSourceConfiguration->contains ?? $table;

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        // Add custom query restrictions
        if ($recipientSourceConfiguration->queryRestrictions) {
            foreach ($recipientSourceConfiguration->queryRestrictions as $queryRestrictionFQN) {
                $queryRestriction = GeneralUtility::makeInstance($queryRestrictionFQN);
                if ($queryRestriction instanceof QueryRestrictionInterface) {
                    $queryBuilder->getRestrictions()->add($queryRestriction);
                }
            }
        }

        $mailActiveExpression = '';
        if (!$recipientSourceConfiguration->ignoreMailActive) {
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
                        $queryBuilder->expr()->and(
                            $mailActiveExpression,
                            $queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')),
                            $queryBuilder->expr()->in('fe_groups.pid',
                                $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY)),
                            $queryBuilder->expr()->inSet('fe_users.usergroup', 'fe_groups.uid', true)
                        )
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
                    $queryBuilder->expr()->and(
                        $mailActiveExpression,
                        $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->in($switchTable . '.pid',
                            $queryBuilder->createNamedParameter($pages, Connection::PARAM_INT_ARRAY))
                    )
                )
                ->orderBy($switchTable . '.uid')
                ->executeQuery()
                ->fetchAllAssociative(), 'uid');
        }

        // get recipients with same categories set in group
        $recipients = [];
        $frontendUserGroups = $table === 'fe_groups' ? array_column(GeneralUtility::makeInstance(FrontendUserGroupRepository::class)->findRecordByPidList($pages,
            ['uid']), 'uid') : [];
        $deletedField = $GLOBALS['TCA'][$table]['ctrl']['delete'] ?? false;
        $disabledField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] ?? false;
        /** @var Category $category */
        foreach ($categories as $category) {
            // collect all recipients containing at least one category of the given group
            $recipientCollection = CategoryCollection::load($category->getUid(), true, $switchTable, 'categories');
            foreach ($recipientCollection as $recipient) {
                if ((!$deletedField || !$recipient[$deletedField]) &&
                    (!$disabledField || !($recipient[$disabledField] ?? false)) &&
                    ($recipientSourceConfiguration->ignoreMailActive || $recipient['mail_active'] ?? true) &&
                    !in_array($recipient['uid'], $recipients) &&
                    in_array($recipient['pid'], $pages) &&
                    ($table !== 'fe_groups' || count(array_intersect($frontendUserGroups,
                            GeneralUtility::intExplode(',', $recipient['usergroup']))) > 0)
                ) {
                    // add it to the list if all constrains fulfilled
                    $recipients[] = $recipient['uid'];
                }
            }
        }
        return $recipients;
    }

    /**
     * Return all uids from $table for a static mail group.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getStaticIdListByTableAndGroupUid(
        RecipientSourceConfigurationDTO $recipientSourceConfiguration,
        int $mailGroupUid,
    ): array {
        $table = $recipientSourceConfiguration->table;
        $queryBuilder = $this->getQueryBuilder($table);
        $mailActiveExpression = '';
        if (!$recipientSourceConfiguration->ignoreMailActive) {
            $switchTable = $recipientSourceConfiguration->contains ?? $table;
            $mailActiveExpression = $queryBuilder->expr()->eq($switchTable . '.mail_active', 1);
        }

        // Add custom query restrictions
        if ($recipientSourceConfiguration->queryRestrictions) {
            foreach ($recipientSourceConfiguration->queryRestrictions as $queryRestrictionFQN) {
                $queryRestriction = GeneralUtility::makeInstance($queryRestrictionFQN);
                if ($queryRestriction instanceof QueryRestrictionInterface) {
                    $queryBuilder->getRestrictions()->add($queryRestriction);
                }
            }
        }

        if ($table !== 'fe_groups') {
            return array_unique(array_column($queryBuilder
                ->select($table . '.uid')
                ->from('tx_mail_group_mm', 'tx_mail_group_mm')
                ->innerJoin(
                    'tx_mail_group_mm',
                    $table,
                    $table,
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign',
                        $queryBuilder->quoteIdentifier($table . '.uid'))
                )
                ->where(
                    $queryBuilder->expr()->and(
                        $mailActiveExpression,
                        $queryBuilder->expr()->neq($table . '.email', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local',
                            $queryBuilder->createNamedParameter($mailGroupUid, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('tx_mail_group_mm.tablenames',
                            $queryBuilder->createNamedParameter($table))
                    )
                )
                ->orderBy($table . '.uid')
                ->executeQuery()
                ->fetchAllAssociative(), 'uid'));
        }

        // handle special case fe_groups

        $idList = array_column($queryBuilder
            ->select('fe_users.uid')
            ->from('tx_mail_group_mm', 'tx_mail_group_mm')
            ->innerJoin(
                'tx_mail_group_mm',
                'fe_groups',
                'fe_groups',
                $queryBuilder->expr()->eq('tx_mail_group_mm.uid_foreign',
                    $queryBuilder->quoteIdentifier('fe_groups.uid'))
            )
            ->innerJoin(
                'fe_groups',
                'fe_users',
                'fe_users',
                $queryBuilder->expr()->inSet('fe_users.usergroup', $queryBuilder->quoteIdentifier('fe_groups.uid'),
                    true)
            )
            ->where(
                $queryBuilder->expr()->and(
                    $mailActiveExpression,
                    $queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local',
                        $queryBuilder->createNamedParameter($mailGroupUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('tx_mail_group_mm.tablenames',
                        $queryBuilder->createNamedParameter($table))
                )
            )
            ->orderBy('fe_users.uid')
            ->executeQuery()
            ->fetchAllAssociative(), 'uid');

        $queryBuilder = $this->getQueryBuilder('fe_groups');

        $frontendUserGroups = array_column($queryBuilder
            ->select('fe_groups.uid')
            ->from('tx_mail_domain_model_group', 'tx_mail_domain_model_group')
            ->leftJoin(
                'tx_mail_domain_model_group',
                'tx_mail_group_mm',
                'tx_mail_group_mm',
                $queryBuilder->expr()->eq('tx_mail_group_mm.uid_local',
                    $queryBuilder->quoteIdentifier('tx_mail_domain_model_group.uid'))
            )
            ->leftJoin(
                'tx_mail_group_mm',
                'fe_groups',
                'fe_groups',
                $queryBuilder->expr()->eq('fe_groups.uid',
                    $queryBuilder->quoteIdentifier('tx_mail_group_mm.uid_foreign'))
            )
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('tx_mail_domain_model_group.uid',
                        $queryBuilder->createNamedParameter($mailGroupUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('tx_mail_group_mm.tablenames',
                        $queryBuilder->createNamedParameter('fe_groups'))
                )
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

                    $queryBuilder = $this->getQueryBuilder('fe_groups');

                    $mailActiveExpression = '';
                    if (!$recipientSourceConfiguration->ignoreMailActive) {
                        // for fe_users and fe_group, only activated newsletter
                        $mailActiveExpression = $queryBuilder->expr()->eq('fe_users.mail_active', 1);
                    }

                    // fetch all fe_users from these subgroups
                    $idList = array_merge($idList, array_column($queryBuilder
                        ->select('fe_users.uid')
                        ->from('fe_groups', 'fe_groups')
                        ->innerJoin(
                            'fe_groups',
                            'fe_users',
                            'fe_users'
                        )
                        ->orWhere(...$subGroupExpressions)
                        ->andWhere(
                            $queryBuilder->expr()->and(
                                $mailActiveExpression,
                                $queryBuilder->expr()->neq('fe_users.email', $queryBuilder->createNamedParameter(''))
                            )
                        )
                        ->orderBy('fe_users.uid')
                        ->executeQuery()
                        ->fetchAllAssociative(), 'uid'));
                }
            }
        }

        return array_unique($idList);
    }


    /*
     * U P D A T E S
     */

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
     * @throws \Doctrine\DBAL\Exception
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
