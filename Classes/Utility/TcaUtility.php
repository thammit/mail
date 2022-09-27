<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\SysLanguageRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtility
{
    /**
     * @throws DBALException
     * @throws Exception
     */
    public static function getLocalizedCategories(array &$params): void
    {
        $sys_language_uid = 0;
        $languageService = LanguageUtility::getLanguageService();
        //initialize backend user language
        $lang = $languageService->lang == 'default' ? 'en' : $languageService->lang;

        if ($lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $sysPage = GeneralUtility::makeInstance(PageRepository::class);
            $rows = GeneralUtility::makeInstance(SysLanguageRepository::class)->selectSysLanguageForSelectCategories(
                $lang,
                $sysPage->enableFields('sys_language'),
                $sysPage->enableFields('static_languages')
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $sys_language_uid = (int)$row['uid'];
                }
            }
        }

        if (is_array($params['items']) && !empty($params['items'])) {
            $table = (string)$params['config']['itemsProcFunc_config']['table'];
            $tempRepository = GeneralUtility::makeInstance(TempRepository::class);

            foreach ($params['items'] as $k => $item) {
                $rows = $tempRepository->findByTableAndUid($table, intval($item[1]));
                if ($rows) {
                    foreach ($rows as $rowCat) {
                        if ($localizedRowCat = RepositoryUtility::getRecordOverlay($table, $rowCat, $sys_language_uid)) {
                            $params['items'][$k][0] = $localizedRowCat['category'];
                        }
                    }
                }
            }
        }
    }

    public static function getDefaultSortByFromTca(string $table): string
    {
        return preg_replace(
            '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
            trim($GLOBALS['TCA'][$table]['ctrl']['default_sortby'])
        );
    }

    /**
     * Get translated label of table column
     * default table: sys_dmail
     *
     * @param string $columnName
     * @param string $table
     * @return string The label
     */
    public static function getTranslatedLabelOfTcaField(string $columnName, string $table = 'sys_dmail'): string
    {
        return stripslashes(LanguageUtility::getLanguageService()->sL(BackendUtility::getItemLabel($table, $columnName)));
    }
}
