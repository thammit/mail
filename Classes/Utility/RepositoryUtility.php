<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RepositoryUtility
{
    /**
     * Compile the categories enables for this $row of this $table.
     *
     * @param string $table Table name
     * @param array $row Row from table
     * @param int $sysLanguageUid User language ID
     *
     * @return array the categories in an array with the cat id as keys
     * @throws DBALException
     * @throws Exception
     */
    public static function makeCategories(string $table, array $row, int $sysLanguageUid): array
    {
        $categories = [];

        $mmField = $table == 'sys_dmail_group' ? 'select_categories' : 'module_sys_dmail_category';

        $pageTsConfig = BackendUtility::getTCEFORM_TSconfig($table, $row);
        if (is_array($pageTsConfig[$mmField])) {
            $pidList = $pageTsConfig[$mmField]['PAGE_TSCONFIG_IDLIST'] ?? [];
            if ($pidList) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_category');
                $res = $queryBuilder->select('*')
                    ->from('sys_dmail_category')
                    ->add('where', 'sys_dmail_category.pid IN (' . str_replace(',', "','", $queryBuilder->createNamedParameter($pidList)) . ')' .
                        ' AND l18n_parent=0')
                    ->execute();
                while ($rowCat = $res->fetch()) {
                    if ($localizedRowCat = self::getRecordOverlay('sys_dmail_category', $rowCat, $sysLanguageUid, '')) {
                        $categories[$localizedRowCat['uid']] = htmlspecialchars($localizedRowCat['category']);
                    }
                }
            }
        }
        return $categories;
    }

    /**
     * Import from t3lib_page in order to create backend version
     * Creates language-overlay for records in general
     * (where translation is found in records from the same table)
     *
     * @param string $table Table name
     * @param array $row Record to overlay. Must contain uid, pid and languageField
     * @param int $sys_language_content Language ID of the content
     * @param string $OLmode Overlay mode. If "hideNonTranslated" then records without translation will not be returned un-translated but unset (and return value is false)
     *
     * @return array Returns the input record, possibly overlaid with a translation. But if $OLmode is "hideNonTranslated" then it will return false if no translation is found.
     * @throws Exception|DBALException
     */
    public static function getRecordOverlay(string $table, array $row, int $sys_language_content, string $OLmode = ''): array
    {
        if ($row['uid'] > 0 && $row['pid'] > 0) {
            if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
                if (!isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'])) {
                    // Will try to overlay a record only
                    // if the sys_language_content value is larger that zero.
                    if ($sys_language_content > 0) {
                        // Must be default language or [All], otherwise no overlaying:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] <= 0) {
                            // Select overlay record:
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $olrow = $queryBuilder->select('*')
                                ->from($table)
                                ->add('where', 'pid=' . intval($row['pid']) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . $sys_language_content .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($row['uid']))
                                ->setMaxResults(1)/* LIMIT 1*/
                                ->execute()
                                ->fetchAssociative();

                            // Merge record content by traversing all fields:
                            if (is_array($olrow)) {
                                foreach ($row as $fN => $fV) {
                                    if ($fN != 'uid' && $fN != 'pid' && isset($olrow[$fN])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'mergeIfNotBlank' || strcmp(trim($olrow[$fN]), ''))) {
                                            $row[$fN] = $olrow[$fN];
                                        }
                                    }
                                }
                            } else if ($OLmode === 'hideNonTranslated' && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0) {
                                // Unset, if non-translated records should be hidden.
                                // ONLY done if the source record really is default language and not [All] in which case it is allowed.
                                unset($row);
                            }

                            // Otherwise, check if sys_language_content is different from the value of the record
                            // that means a japanese site might try to display french content.
                        } else if ($sys_language_content != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                            unset($row);
                        }
                    } else {
                        // When default language is displayed,
                        // we never want to return a record carrying another language!:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
                            unset($row);
                        }
                    }
                }
            }
        }

        return $row;
    }
}
