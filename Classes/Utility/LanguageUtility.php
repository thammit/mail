<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
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
    public static function getAvailablePageLanguages(int $pageUid): array
    {
        $availablePageLanguages = [];
        $languages = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)->getSystemLanguages();
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        // loop through all sys languages and check if there is matching page translation
        foreach ($languages as $language) {
            // we skip -1
            if ($language['uid'] < 0) {
                continue;
            }

            // 0 is always present so only for > 0
            if ($language['uid'] > 0) {
                if (empty($pageRepository->getPageOverlay($pageUid, $language['uid']))) {
                    continue;
                }
            }

            $availablePageLanguages[$language['uid']] = $language;
        }

        return $availablePageLanguages;
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
     * @param string $languageLabelPrefix
     * @return string
     */
    public static function getLL(string $index, string $languageLabelPrefix = 'LLL:EXT:mail/Resources/Private/Language/Modules.xlf:'): string
    {
        return self::getLanguageService()->sL($languageLabelPrefix . $index);
    }
}
