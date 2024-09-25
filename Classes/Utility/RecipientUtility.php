<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Model\CategoryInterface;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\CategoryRepository;
use MEDIAESSENZ\Mail\Type\Enumeration\CategoryFormat;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class RecipientUtility
{
    /**
     * @throws InvalidQueryException
     * @throws Exception
     */
    public function getRecipientCategories(string $table, int $uid, int $categoryFormat): array|string
    {
        return match ($categoryFormat) {
            CategoryFormat::OBJECTS => RecipientUtility::getObjectsOfRecipientCategories($table, $uid),
            CategoryFormat::UIDS => RecipientUtility::getListOfRecipientCategories($table, $uid),
            CategoryFormat::CSV => RecipientUtility::getCsvOfRecipientCategories($table, $uid),
            default => '',
        };
    }

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
     * @throws Exception
     * @throws InvalidQueryException
     */
    public static function getObjectsOfRecipientCategories(string $table, int $uid): array
    {
        $categoryUidList = self::getListOfRecipientCategories($table, $uid);
        $categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);
        return $categoryRepository->findByUids($categoryUidList)->toArray();
    }

    /**
     * @throws Exception
     * @throws InvalidQueryException
     */
    public static function getCsvOfRecipientCategories(string $table, int $uid, $orderings = ['title' => 'ASC']): string
    {
        $categoryUidList = self::getListOfRecipientCategories($table, $uid);
        $categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);
        $categories = $categoryRepository->findByUids($categoryUidList, $orderings);
        if ($categories->count() === 0) {
            return '';
        }
        $categoryTitles = [];
        foreach ($categories as $category) {
            $categoryTitles[] = $category->getTitle();
        }

        return implode(', ', $categoryTitles);
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
        return array_reduce($uidLists, fn($total, $uidList) => $total + (is_array($uidList) ? count($uidList) : 0), 0);
//        $totalRecipients = 0;
//        foreach ($uidLists as $uidList) {
//            if (is_array($uidList)) {
//                $totalRecipients += count($uidList);
//            }
//        }
//
//        return $totalRecipients;
    }

    /**
     * Normalize a list of email addresses separated by colon, semicolon or enter (chr10) and remove not valid emails
     *
     * @param string $emailAddresses
     * @return string
     */
    public static function normalizeListOfEmailAddresses(string $emailAddresses): string
    {
        // remove duplicates and return clean list
        return implode(',', array_keys(array_flip(array_map('trim', preg_split('|[' . LF . ',;]|', $emailAddresses)))));
    }

    /**
     * Normalize an array of email addresses into a 2-dimensional array with an optional empty name element
     */
    public static function normalizePlainEmailList(array $emails, $addEmptyNameColumn = false): array
    {
        $data = array_map(fn($email) => ['email' => trim($email)], $emails);

        if ($addEmptyNameColumn) {
            $data = array_map(fn($subArray) => $subArray + ['name' => ''], $data);
        }

        return $data;
    }

    public static function invalidateEmail($email, $errorCodes = []): string
    {
        return '!invalid!-' . implode('-', $errorCodes) . '-' . str_replace('@', '[at]', $email);
    }

    public static function removeInvalidateEmailsFromRecipientsList(array $recipientsList): array
    {
        return array_filter($recipientsList, fn($entry) => GeneralUtility::validEmail($entry['email']));
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
     * @param int $categoryFormat
     * @return array
     */
    public static function getFlatRecipientModelData(RecipientInterface|DomainObjectInterface $recipient, array $getters, int $categoryFormat = CategoryFormat::CSV): array
    {
        $values = [];
        foreach ($getters as $field => $getter) {
            switch ($field) {
                case 'categories':
                    $data = [];
                    if ($recipient instanceof CategoryInterface) {
                        $categories = $recipient->getCategories();
                        if ($categories->count() > 0) {
                            foreach ($categories as $category) {
                                switch ($categoryFormat) {
                                    case CategoryFormat::UIDS:
                                        $data[] = $category->getUid();
                                        break;
                                    case CategoryFormat::CSV:
                                        $data[] = $category->getTitle();
                                        break;
                                    case CategoryFormat::OBJECTS:
                                        $data[] = $category;
                                        break;
                                }
                            }
                        }
                    }
                    $values['categories'] = $categoryFormat === CategoryFormat::CSV ? implode(', ', $data) : $data;
                    break;
                default:
                    $value = $recipient->$getter();
                    if ($value instanceof ObjectStorage) {
                        $data = [];
                        if ($value->count() > 0) {
                            foreach ($value as $item) {
                                if (method_exists($item, 'getTitle')) {
                                    $data[] = $item->getTitle();
                                } else {
                                    if (method_exists($item, 'getName')) {
                                        $data[] = $item->getName();
                                    } else {
                                        $data[] = $item->getUid();
                                    }
                                }
                            }
                        }
                        $values[$field] = implode(', ', $data);
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
        }

        return $values;
    }
}
