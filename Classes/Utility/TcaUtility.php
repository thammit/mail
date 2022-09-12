<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\SysLanguageRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
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
        $languageService = MailerUtility::getLanguageService();
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
                $rows = $tempRepository->selectRowsByUid($table, intval($item[1]));
                if ($rows) {
                    foreach ($rows as $rowCat) {
                        if ($localizedRowCat = $tempRepository->getRecordOverlay($table, $rowCat, $sys_language_uid)) {
                            $params['items'][$k][0] = $localizedRowCat['category'];
                        }
                    }
                }
            }
        }
    }
}
