<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserGroupRepository;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class BackendDataUtility
{
    public static function getBaseUrl(int $pageId, int $languageUid = 0): string
    {
        if ($pageId > 0) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            try {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();
                if ($languageUid > 0) {
                    $siteLanguage = $site->getLanguageById($languageUid);
                    $languagePath = rtrim($siteLanguage->getBase()->getPath(), '/');
                    if ($languagePath) {
                        return sprintf('%s://%s/%s', $base->getScheme(), $base->getHost(), $languagePath);
                    }
                }

                return sprintf('%s://%s', $base->getScheme(), $base->getHost());
            } catch (SiteNotFoundException) {
            }
        }

        return '';
    }

    /**
     * @param int $pageUid
     * @param string $params
     * @param int $simulateUserGroup
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getUrlForInternalPage(int $pageUid, string $params, int $simulateUserGroup = 0): string
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $params = str_starts_with($params, '&') ? substr($params, 1) : $params;

        if ($simulateUserGroup) {
            $params .= '&mail_fe_group=' . $simulateUserGroup . '&access_token=' . RegistryUtility::createAndGetAccessToken();
        }

        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid . '&' . $params,
            'forceAbsoluteUrl' => true,
            'forceAbsoluteUrl.' => ['scheme' => ConfigurationUtility::getDefaultScheme()],
            'linkAccessRestrictedPages' => true,
        ]);
    }

    public static function addToolTipData(array $pages): array
    {
        $frontendUserGroupRepository = GeneralUtility::makeInstance(FrontendUserGroupRepository::class);
        foreach ($pages as $key => $page) {
            $toolTip = 'id=' . $page['uid'];
            if ($page['fe_group']) {
                $frontendUserGroupTitles = [];
                $frontendUserGroupUids = GeneralUtility::intExplode(',', $page['fe_group'], true);
                foreach ($frontendUserGroupUids as $frontendUserGroupUid) {
                    $frontendUserGroup = $frontendUserGroupRepository->findRecordByUid($frontendUserGroupUid, ['title'], true);
                    $frontendUserGroupTitles[] = $frontendUserGroup[0]['title'];
                }
                $toolTip .= ' - ' . LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.fe_group') . ' ' . implode(', ', $frontendUserGroupTitles);
            }
            $pages[$key]['toolTip'] = $toolTip;
        }

        return $pages;
    }
}
