<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class BackendDataUtility
{
    public static function getBaseUrl(int $pageId): string
    {
        if ($pageId > 0) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            try {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();

                return sprintf('%s://%s', $base->getScheme(), $base->getHost());
            } catch (SiteNotFoundException $exception) {
            }
        }

        return '';
    }

    /**
     * @param int $pageUid
     * @param string $params
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getUrlForInternalPage(int $pageUid, string $params): string
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $params = str_starts_with($params, '&') ? substr($params, 1) : $params;

        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid . '&' . $params,
            'forceAbsoluteUrl' => true,
            'forceAbsoluteUrl.' => ['scheme' => ConfigurationUtility::getDefaultScheme()],
            'linkAccessRestrictedPages' => true,
        ]);
    }

    /**
     * @param int $pageUid
     * @return string
     */
    public static function getAbsoluteBaseUrlForMailPage(int $pageUid): string
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        // Finding the domain to use
        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid,
            'forceAbsoluteUrl' => true,
            'linkAccessRestrictedPages' => true,
        ]);
    }

    /**
     * Get the ID of page in a tree
     *
     * @param int $id Page ID
     * @param string $perms_clause Select query clause
     * @return array the page ID, recursively
     */
    public static function getRecursiveSelect(int $id, string $perms_clause): array
    {
        // Finding tree and offer setting of values recursively.
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init('AND ' . $perms_clause);
        $tree->makeHTML = 0;
        $tree->setRecs = 0;
        $getLevels = 10000;
        $tree->getTree($id, $getLevels);

        return $tree->ids;
    }
}
