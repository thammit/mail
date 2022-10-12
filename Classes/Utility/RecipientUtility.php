<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Enumeration\RecipientGroupType;
use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class RecipientUtility
{

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail record
     * @param int $recipientUid Recipient UID
     * @param string $table Recipient table
     *
     * @return bool Number of found records
     * @throws DBALException
     */
    public static function isMailSendToRecipient(int $mailUid, int $recipientUid, string $table): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mail_domain_model_log');

        $statement = $queryBuilder
            ->select('uid')
            ->from('tx_mail_domain_model_log')
            ->where($queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($recipientUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('recipient_table', $queryBuilder->createNamedParameter($table)))
            ->andWhere($queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        return (bool)$statement->rowCount();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     * @throws DBALException
     */
    public static function finalSendingGroups(int $pageId, int $sysLanguageUid, string|int $userTable, $backendUserPermission): array
    {
        $mailGroups = [];
        $groups = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->findSysDmailGroupUidsForFinalMail(
            $pageId,
            $sysLanguageUid,
            trim($GLOBALS['TCA']['tx_mail_domain_model_group']['ctrl']['default_sortby'])
        );
        if ($groups) {
            foreach ($groups as $group) {
                $result = self::compileMailGroup([$group], $userTable, $backendUserPermission);
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
                $mailGroups[] = ['uid' => $group['uid'], 'title' => $group['title'], 'receiver' => $totalRecipients];
            }
        }

        return $mailGroups;
    }

    /**
     * Normalize address
     * fe_user and tt_address are using different field names for the same information
     *
     * @param array $recipientData Recipient's data array
     *
     * @return array Fixed recipient's data array
     */
    public static function normalizeAddress(array $recipientData): array
    {
        // Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
        if ($recipientData['telephone'] ?? false) {
            $recipientData['phone'] = $recipientData['telephone'];
        }

        // Firstname must be more than 1 character
        $token = strtok(trim($recipientData['name']), ' ');
        $recipientData['firstname'] = $token ? trim($token) : '';
        if (strlen($recipientData['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['name'];
        }
        if (!trim($recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['email'];
        }
        return $recipientData;
    }

    /**
     * Normalize a list of email addresses separated by colon, semicolon or enter (chr10) and remove not valid emails
     *
     * @param string $emailAddresses
     * @return string
     */
    public static function normalizeListOfEmailAddresses(string $emailAddresses): string
    {
        $addresses = preg_split('|[' . chr(10) . ',;]|', $emailAddresses);

        foreach ($addresses as $key => $val) {
            $addresses[$key] = trim($val);
            if (!GeneralUtility::validEmail($addresses[$key])) {
                unset($addresses[$key]);
            }
        }

        return implode(',', array_keys(array_flip($addresses)));
    }

    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table Tablename of the recipient
     * @param int $uid Uid of the recipient
     *
     * @return string        list of categories
     * @throws DBALException
     * @throws Exception
     */
    public static function getListOfRecipientCategories(string $table, int $uid): string
    {
        if ($table === 'PLAINLIST') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select($relationTable . '.uid_foreign')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_local = ' . $table . '.uid')
            ->where($queryBuilder->expr()->eq($relationTable . '.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->execute();

        $list = '';
        while ($row = $statement->fetchAssociative()) {
            $list .= $row['uid_foreign'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * Standard authentication code (used in Direct Mail, checkJumpUrl and setfixed links computations)
     *
     * @param int|array $uid_or_record Uid (int) or record (array)
     * @param string $fields List of fields from the record if that is given.
     * @param int $codeLength Length of returned authentication code.
     * @return string MD5 hash of 8 chars.
     */
    public static function stdAuthCode(int|array $uid_or_record, string $fields = '', int $codeLength = 8): string
    {
        if (is_array($uid_or_record)) {
            $recCopy_temp = [];
            if ($fields) {
                $fieldArr = GeneralUtility::trimExplode(',', $fields, true);
                foreach ($fieldArr as $k => $v) {
                    $recCopy_temp[$k] = $uid_or_record[$v];
                }
            } else {
                $recCopy_temp = $uid_or_record;
            }
            $preKey = implode('|', $recCopy_temp);
        } else {
            $preKey = $uid_or_record;
        }
        $authCode = $preKey . '||' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        return substr(md5($authCode), 0, $codeLength);
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
    public static function getSingleMailGroup(int $groupUid, string $userTable, string $backendUserPermissions): array
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
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
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
                            $recipients = self::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = self::removeDuplicates($recipients);
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
                            $collect = self::getSingleMailGroup($groupUid, $userTable, $backendUserPermissions);
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
     * @param array $groups
     * @param string $userTable
     * @param string $backendUserPermissions
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function compileMailGroup(array $groups, string $userTable, string $backendUserPermissions): array
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

            $recipientList = self::getSingleMailGroup($groupId, $userTable, $backendUserPermissions);

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
            $idLists['PLAINLIST'] = self::removeDuplicates($idLists['PLAINLIST']);
        }

        return $idLists;
    }

    /**
     * Rearrange emails array into a 2-dimensional array
     *
     * @param array $plainMails Recipient emails
     *
     * @return array a 2-dimensional array consisting email and name
     */
    public static function reArrangePlainMails(array $plainMails): array
    {
        $out = [];
        $c = 0;
        foreach ($plainMails as $v) {
            $out[$c]['email'] = trim($v);
            $out[$c]['name'] = '';
            $c++;
        }
        return $out;
    }

    /**
     * Remove double record in an array
     *
     * @param array $plainlist Email of the recipient
     *
     * @return array Cleaned array
     */
    public static function removeDuplicates(array $plainlist): array
    {
        /**
         * $plainlist is a multidimensional array.
         * this method only remove if a value has the same array
         * $plainlist = [
         *        0 => [
         *            name => '',
         *            email => '',
         *        ],
         *        1 => [
         *            name => '',
         *            email => '',
         *        ],
         * ];
         */
        return array_map('unserialize', array_unique(array_map('serialize', $plainlist)));
    }
}
