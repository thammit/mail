<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LanguageUtility
{

    /**
     * Get available languages for a page
     *
     * @param $pageUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public static function getAvailablePageLanguages($pageUid): array
    {
        static $languages;
        $languageUids = [];

        if ($languages === null) {
            $languages = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)->getSystemLanguages();
        }

        // loop through all sys languages and check if there is matching page translation
        foreach ($languages as $lang) {
            // we skip -1
            if ((int)$lang['uid'] < 0) {
                continue;
            }

            // 0 is always present so only for > 0
            if ((int)$lang['uid'] > 0) {
                $langRow = GeneralUtility::makeInstance(PagesRepository::class)->selectPageByL10nAndSysLanguageUid($pageUid, $lang['uid']);

                if (empty($langRow)) {
                    continue;
                }
            }

            $languageUids[(int)$lang['uid']] = $lang;
        }

        return $languageUids;
    }

    /**
     * @return LanguageService
     */
    public static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param string $index
     * @return string
     */
    public static function getLL(string $index): string
    {
        return self::getLanguageService()->sL($index);
    }
}
