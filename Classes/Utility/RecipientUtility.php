<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Model\CategoryInterface;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Database\Connection;
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
                $queryBuilder->expr()->eq($relationTable . '.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($relationTable . '.tablenames', $queryBuilder->createNamedParameter($table))
            )
            ->executeQuery();

        $recipientCategories = [];
        while ($row = $statement->fetchAssociative()) {
            $recipientCategories[] = (int)$row['uid_local'];
        }

        return $recipientCategories;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function getAllRecipientFields(): array
    {
        $defaultRecipientFields = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipientFields'), true);
        return array_merge($defaultRecipientFields, GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('additionalRecipientFields'), true));
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
        $rawAddressList = preg_split('|[' . chr(10) . ',;]|', $emailAddresses);
        $cleanAddressList = [];

        foreach ($rawAddressList as $email) {
            $email = trim($email);
            if (GeneralUtility::validEmail($email)) {
                $cleanAddressList[] = $email;
            }
        }

        // remove duplicates and return clean list
        return implode(',', array_keys(array_flip($cleanAddressList)));
    }

    /**
     * authentication code
     *
     * @param array $record record
     * @param string $fields list of fields
     * @param int $codeLength length of returned authentication code
     * @return string hash
     */
    public static function stdAuthCode(array $record, string $fields = '', int $codeLength = 8): string
    {
        $prefixFields = [];
        if ($fields) {
            $fieldArray = GeneralUtility::trimExplode(',', $fields, true);
            foreach ($fieldArray as $key => $value) {
                $prefixFields[$key] = $record[$value];
            }
        } else {
            $prefixFields = $record;
        }
        $prefix = implode('|', $prefixFields);
        $authCode = $prefix . '||' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];

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
        foreach ($plainMails as $email) {
            $out[] = ['email' => trim($email), 'name' => ''];
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
}
