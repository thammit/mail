<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\CategoryInterface;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use PDO;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class RecipientUtility
{
    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table table of the recipient (tt_address or fe_users)
     * @param int $uid Uid of the recipient
     *
     * @return array list of categories
     * @throws DBALException
     * @throws Exception
     */
    public static function getListOfRecipientCategories(string $table, int $uid): array
    {
        $relationTable = $GLOBALS['TCA'][$table]['columns']['categories']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select($relationTable . '.uid_local')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_foreign = ' . $table . '.uid')
            ->where(
                $queryBuilder->expr()->eq($relationTable . '.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq($relationTable . '.uid_foreign', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->execute();

        $recipientCategories = [];
        while ($row = $statement->fetchAssociative()) {
            $recipientCategories[] = (int)$row['uid_local'];
        }

        return $recipientCategories;
    }


    /**
     * @param array $uidLists
     * @return int
     */
    public static function calculateTotalRecipientsOfUidLists(array $uidLists): int
    {
        $totalRecipients = 0;
        foreach ($uidLists as $uidList) {
            if (is_array($uidList)) {
                $totalRecipients += count($uidList);
            }
        }

        return $totalRecipients;
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
     * @param array $plainList Email of the recipient
     *
     * @return array Cleaned array
     */
    public static function removeDuplicates(array $plainList): array
    {
        /*
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
        return array_map('unserialize', array_unique(array_map('serialize', $plainList)));
    }

    /**
     * @param RecipientInterface|DomainObjectInterface $recipient
     * @param array $getters
     * @param bool $withCategoryUidsArray
     * @return array
     */
    public static function getFlatRecipientModelData(RecipientInterface|DomainObjectInterface $recipient, array $getters, bool $withCategoryUidsArray = false): array
    {
        $values = [];
        foreach ($getters as $field => $getter) {
            $categoryUids = $withCategoryUidsArray && $field === 'categories';
            if ($field === 'categories' && !$recipient instanceof CategoryInterface && !method_exists($recipient, $getter)) {
                $values[$field] = $categoryUids ? [] : '';
                continue;
            }
            $value = $recipient->$getter();
            if ($value instanceof ObjectStorage) {
                if ($value->count() > 0) {
                    $titles = [];
                    foreach ($value as $item) {
                        if ($categoryUids) {
                            $titles[] = $item->getUid();
                        } else {
                            if (method_exists($item, 'getTitle')) {
                                $titles[] = $item->getTitle();
                            } else {
                                if (method_exists($item, 'getName')) {
                                    $titles[] = $item->getName();
                                } else {
                                    $titles[] = $item->getUid();
                                }
                            }
                        }
                    }
                    if ($categoryUids) {
                        $values[$field] = $titles;
                    } else {
                        $values[$field] = implode(', ', $titles);
                    }
                } else {
                    $values[$field] = $categoryUids ? [] : '';
                }
            } else {
                if (is_bool($value)) {
                    $values[$field] = $value ? '1' : '0';
                } else {
                    if ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
                        $values[$field] = $value->format('c');
                    } else {
                        $values[$field] = (string)$value;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @param string $pagesCSV
     * @param bool $recursive
     * @return array
     */
    public static function getRecursivePagesList(string $pagesCSV, bool $recursive): array
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

}
