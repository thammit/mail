<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecipientService
{
    protected int $pageId = 0;
    protected SysDmailGroupRepository $sysDmailGroupRepository;
    protected EventDispatcherInterface $eventDispatcher;
    protected string $backendUserPermissions;

    public function __construct(
        SysDmailGroupRepository $sysDmailGroupRepository = null,
        EventDispatcherInterface $eventDispatcher = null
    )
    {
        $this->sysDmailGroupRepository = $sysDmailGroupRepository ?? GeneralUtility::makeInstance(SysDmailGroupRepository::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
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
