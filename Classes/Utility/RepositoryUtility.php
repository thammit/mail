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
    public static function getCategories(string $table, array $row, int $sysLanguageUid): array
    {
        $categories = [];

        $pageTsConfig = BackendUtility::getTCEFORM_TSconfig($table, $row);
        if (is_array($pageTsConfig['categories'])) {
            $pidList = $pageTsConfig['categories']['PAGE_TSCONFIG_IDLIST'] ?? [];
            if ($pidList) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
                $res = $queryBuilder->select('*')
                    ->from('sys_category')
                    ->where(
                        $queryBuilder->expr()->in('pid', str_replace(',', "','", $queryBuilder->createNamedParameter($pidList))),
                        $queryBuilder->expr()->eq('l10n_parent', 0)
                    )
                    ->execute();
                while ($rowCat = $res->fetchAssociative()) {
                    if ($localizedRowCat = self::getRecordOverlay('sys_category', $rowCat, $sysLanguageUid)) {
                        $categories[$localizedRowCat['uid']] = $localizedRowCat['title'];
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
     * @param int $sysLanguageUid Language Uid of the content
     *
     * @return array Returns the input record, possibly overlaid with a translation. But if $OLmode is "hideNonTranslated" then it will return false if no translation is found.
     * @throws Exception|DBALException
     */
    public static function getRecordOverlay(string $table, array $row, int $sysLanguageUid): array
    {
        if ($row['uid'] > 0 && $row['pid'] > 0) {
            if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
                if (!isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'])) {
                    // Will try to overlay a record only
                    // if the sys_language_content value is larger that zero.
                    if ($sysLanguageUid > 0) {
                        // Must be default language or [All], otherwise no overlaying:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] <= 0) {
                            // Select overlay record:
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $overlayRow = $queryBuilder->select('*')
                                ->from($table)
                                ->add('where', 'pid=' . intval($row['pid']) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . $sysLanguageUid .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($row['uid']))
                                ->setMaxResults(1)/* LIMIT 1*/
                                ->execute()
                                ->fetchAssociative();

                            // Merge record content by traversing all fields:
                            if (is_array($overlayRow)) {
                                foreach ($row as $fN => $fV) {
                                    if ($fN != 'uid' && $fN != 'pid' && isset($overlayRow[$fN])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'mergeIfNotBlank' || strcmp(trim($overlayRow[$fN]), ''))) {
                                            $row[$fN] = $overlayRow[$fN];
                                        }
                                    }
                                }
                            }

                            // Otherwise, check if sys_language_content is different from the value of the record
                            // that means a japanese site might try to display french content.
                        } else if ($sysLanguageUid != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
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
