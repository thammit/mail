<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
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
        if ($simulateUserGroup) {
            $params .= '&mail_fe_group=' . $simulateUserGroup . '&access_token=' . RegistryUtility::createAndGetAccessToken();
        }
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid . '&' . ltrim($params, '&'),
            'forceAbsoluteUrl' => true,
            'forceAbsoluteUrl.' => ['scheme' => ConfigurationUtility::getDefaultScheme()],
            'linkAccessRestrictedPages' => true,
        ]);
    }

    public static function addToolTipData(array $pages): array
    {
        foreach ($pages as $key => $page) {
            $pages[$key]['toolTip'] = BackendUtility::titleAttribForPages($page, '', false);
        }

        return $pages;
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

    /**
     * @param int $id
     * @return int|bool
     */
    public static function getClosestMailModulePageId(int $id): int|bool
    {
        if (!$id) {
            // it's the root page 0 -> search for the first page with module mail where the current backend user has access to
            $mailModulePageUids = GeneralUtility::makeInstance(PagesRepository::class)->findMailModulePageUids();
            if ($mailModulePageUids) {
                foreach ($mailModulePageUids as $pageUid) {
                    $hasAccess = BackendUtility::readPageAccess($pageUid, $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)) !== false;
                    if ($hasAccess) {
                        return (int)$pageUid;
                    }
                }
            }
            return false;
        }

        $rootLine = BackendUtility::BEgetRootLine($id);
        array_shift($rootLine);
        rsort($rootLine);
        foreach ($rootLine as $page) {
            if ($page['module'] === Constants::MAIL_MODULE_NAME) {
                return (int)$page['uid'];
            }
        }

        return false;
    }
}
