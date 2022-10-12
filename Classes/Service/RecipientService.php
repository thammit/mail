<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class RecipientService
{
    protected int $pageId = 0;
    protected string $backendUserPermissions;

    public function __construct(
        protected GroupRepository $groupRepository,
        protected EventDispatcherInterface $eventDispatcher
    )
    {
        $this->backendUserPermissions = BackendUserUtility::backendUserPermissions();
    }

    /**
     * @param int $pageId
     */
    public function setPageId(int $pageId): void
    {
        $this->pageId = $pageId;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     * @throws DBALException
     */
    public function getFinalSendingGroups(string|int $userTable, $backendUserPermission): array
    {
        $mailGroups = [];
        $groups = $this->groupRepository->findByPid($this->pageId);
        if ($groups) {
            /** @var Group $group */
            foreach ($groups as $group) {
                $result = RecipientUtility::compileMailGroup([$group->getUid()], $userTable, $backendUserPermission);
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
     * @param string $backendUserPermissions
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getQueryInfoIdLists(array $groups, string $userTable, string $backendUserPermissions): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (!count($groups)) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            // Testing to see if group ID is a valid integer, if not - skip to next group ID
            $groupId = MathUtility::convertToPositiveInteger($group['uid'] ?? $group);
            if (!$groupId) {
                continue;
            }

            $recipientList = $this->getSingleMailGroup($groupId, $userTable, $backendUserPermissions);

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
     * Fetches recipient IDs from a given group ID
     * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
     *
     * @param int $groupUid Recipient group ID
     * @param string $userTable
     * @param string $backendUserPermissions
     * @return array List of recipient IDs
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getSingleMailGroup(int $groupUid, string $userTable, string $backendUserPermissions): array
    {
        $idLists = [];
        if ($groupUid) {
            $sysDmailGroupRepository = GeneralUtility::makeInstance(SysDmailGroupRepository::class);
            $mailGroup = $sysDmailGroupRepository->findByUid($groupUid);

            if (is_array($mailGroup)) {
                $tempRepository = GeneralUtility::makeInstance(TempRepository::class);
                switch ($mailGroup['type']) {
                    case RecipientGroupType::PAGES:
                        // From pages
                        // use current page if not set in mail group
                        $thePages = $mailGroup['pages'];
                        // Explode the pages
                        $pages = GeneralUtility::intExplode(',', $thePages);
                        $pageIdArray = [];

                        foreach ($pages as $pageUid) {
                            if ($pageUid > 0) {
                                $pageinfo = BackendUtility::readPageAccess($pageUid, $backendUserPermissions);
                                if (is_array($pageinfo)) {
                                    $pageIdArray[] = $pageUid;
                                    if ($mailGroup['recursive']) {
                                        $pageIdArray = array_merge($pageIdArray, BackendDataUtility::getRecursiveSelect($pageUid, $backendUserPermissions));
                                    }
                                }
                            }
                        }
                        // Remove any duplicates
                        $pageIdArray = array_unique($pageIdArray);
                        $pidList = implode(',', $pageIdArray);

                        // Make queries
                        if ($pidList) {
                            $whichTables = intval($mailGroup['record_types']);
                            if ($whichTables & 1) {
                                // tt_address
                                $idLists['tt_address'] = $tempRepository->getIdList('tt_address', $pidList, $groupUid, $mailGroup['categories']);
                            }
                            if ($whichTables & 2) {
                                // fe_users
                                $idLists['fe_users'] = $tempRepository->getIdList('fe_users', $pidList, $groupUid, $mailGroup['categories']);
                            }
                            if ($userTable && ($whichTables & 4)) {
                                // user table
                                $idLists[$userTable] = $tempRepository->getIdList($userTable, $pidList, $groupUid, $mailGroup['categories']);
                            }
                            if ($whichTables & 8) {
                                // fe_groups
                                $idLists['fe_users'] = $tempRepository->getIdList('fe_groups', $pidList, $groupUid, $mailGroup['categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users']));
                            }
                        }
                        break;
                    case RecipientGroupType::CSV:
                        // List of mails
                        if ($mailGroup['csv'] == 1) {
                            $dmCsvUtility = GeneralUtility::makeInstance(CsvUtility::class);
                            $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroup['list']));
                        } else {
                            $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($recipients);
                        break;
                    case RecipientGroupType::STATIC:
                        // Static MM list
                        $idLists['tt_address'] = $tempRepository->getStaticIdList('tt_address', $groupUid);
                        $idLists['fe_users'] = $tempRepository->getStaticIdList('fe_users', $groupUid);
                        $tempGroups = $tempRepository->getStaticIdList('fe_groups', $groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                        if ($userTable) {
                            $idLists[$userTable] = $tempRepository->getStaticIdList($userTable, $groupUid);
                        }
                        break;
                    case RecipientGroupType::QUERY:
                        // Special query list
                        // Todo Remove that shit!
                        $queryTable = GeneralUtility::_GP('SET')['queryTable'];
                        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');
                        $mailGroup = $sysDmailGroupRepository->updateMailGroup($mailGroup, $userTable, $queryTable, $queryConfig);
                        $whichTables = intval($mailGroup['record_types']);
                        $table = '';
                        if ($whichTables & 1) {
                            $table = 'tt_address';
                        } else {
                            if ($whichTables & 2) {
                                $table = 'fe_users';
                            } else {
                                if ($userTable && ($whichTables & 4)) {
                                    $table = $userTable;
                                }
                            }
                        }
                        if ($table) {
                            $idLists[$table] = $tempRepository->getSpecialQueryIdList($table, $mailGroup);
                        }
                        break;
                    case RecipientGroupType::OTHER:
                        $groups = array_unique($tempRepository->getMailGroups($mailGroup['children'], [$mailGroup['uid']], $backendUserPermissions));
                        foreach ($groups as $groupUid) {
                            $collect = $this->getSingleMailGroup($groupUid, $userTable, $backendUserPermissions);
                            if (is_array($collect)) {
                                $idLists = array_merge_recursive($idLists, $collect);
                            }
                        }
                        break;
                    default:
                }
            }
        }
        return $idLists;
    }


    /**
     * Get recipient ids of groups
     *
     * @param array $groups List of selected group IDs
     *
     * @return array list of the recipient ID
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getRecipientIdsOfMailGroups(array $groups, string $userTable = ''): array
    {
        $recipientIds = RecipientUtility::compileMailGroup($groups, $userTable, $this->backendUserPermissions);

        // Todo: Add PSR-14 EventDispatcher to manipulate the id list (see commented hook code block below)

        return $recipientIds;
//        return [
//            'queryInfo' => ['id_lists' => $idLists],
//        ];
//
//        // If supplied with an empty array, quit instantly as there is nothing to do
//        if (!count($groups)) {
//            return [];
//        }
//
//        // Looping through the selected array, in order to fetch recipient details
//        $idLists = [];
//        foreach ($groups as $groupUid) {
//            // Testing to see if group ID is a valid integer, if not - skip to next group ID
//            $groupUid = MathUtility::convertToPositiveInteger($groupUid);
//            if (!$groupUid) {
//                continue;
//            }
//
//            $recipientList = $this->getSingleMailGroup($groupUid);
//            if (!is_array($recipientList)) {
//                continue;
//            }
//
//            $idLists = array_merge_recursive($idLists, $recipientList);
//        }
//
//        // Make unique entries
//        if (is_array($idLists['tt_address'] ?? false)) {
//            $idLists['tt_address'] = array_unique($idLists['tt_address']);
//        }
//
//        if (is_array($idLists['fe_users'] ?? false)) {
//            $idLists['fe_users'] = array_unique($idLists['fe_users']);
//        }
//
//        if (is_array($idLists[$this->userTable] ?? false) && $this->userTable) {
//            $idLists[$this->userTable] = array_unique($idLists[$this->userTable]);
//        }
//
//        if (is_array($idLists['PLAINLIST'] ?? false)) {
//            $idLists['PLAINLIST'] = MailerUtility::removeDuplicates($idLists['PLAINLIST']);
//        }
//
//        /**
//         * Hook for cmd_compileMailGroup
//         * manipulate the generated id_lists
//         */
//        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] ?? false)) {
//            $hookObjectsArr = [];
//            $temporaryList = '';
//
//            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] as $classRef) {
//                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
//            }
//            foreach ($hookObjectsArr as $hookObj) {
//                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
//                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $groups);
//                }
//            }
//
//            unset($idLists);
//            $idLists = $temporaryList;
//        }
//
//        return [
//            'queryInfo' => ['id_lists' => $idLists],
//        ];
    }
}
