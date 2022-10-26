<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use InvalidArgumentException;
use MEDIAESSENZ\Mail\Service\EnvironmentService;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\TypoScriptAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final class FrontendUtility
{
    public static ?\Throwable $lastError = null;

    /**
     * Initialize Core's global variables to simulate a frontend request to $pageUid
     *
     * @param int $pageUid The page the request should be created for (preferably a root page)
     * @return bool
     */
    public static function buildFakeFE(int $pageUid): bool
    {
        if (!$pageUid) {
            throw new InvalidArgumentException('You must specify a page id.');
        }

        // do not touch anything if we have a TSFE already (wherever it may come from)
        if (isset($GLOBALS['TSFE'])) {
            return true;
        }

        // remove the current PageRenderer singleton (it may be one from the backend context)
        $pageRendererBackup = GeneralUtility::makeInstance(PageRenderer::class);
        $instances = GeneralUtility::getSingletonInstances();
        unset($instances[PageRenderer::class]);
        GeneralUtility::resetSingletonInstances($instances);

        $requestBackup = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $validTSFE = false;
        try {
            $context = GeneralUtility::makeInstance(Context::class);
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageUid);
            $siteLanguage = $site->getDefaultLanguage();
            $pageArgs = new PageArguments($pageUid, '0', []);

            /** @var TypoScriptAspect $aspect */
            $aspect = GeneralUtility::makeInstance(TypoScriptAspect::class, true);
            $context->setAspect('typoscript', $aspect);

            // simulate a normal FE without any logged-in FE or BE user
            $uri = $site->getBase();
            $request = new ServerRequest(
                $uri,
                'GET',
                'php://input',
                [],
                [
                    'HTTP_HOST' => $uri->getHost(),
                    'SERVER_NAME' => $uri->getHost(),
                    'HTTPS' => $uri->getScheme() === 'https',
                    'SCRIPT_FILENAME' => __FILE__,
                    'SCRIPT_NAME' => rtrim($uri->getPath(), '/') . '/'
                ]
            );

            // needed by Extbase UriBuilder to really believe it's a frontend request
            $GLOBALS['TYPO3_REQUEST'] = $request
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('site', $site);

            // some link generation relies on HTTP_HOST, so make sure we simulate the HTTP_HOST that matches our request
            $_SERVER['HTTP_HOST'] = $uri->getHost();

            $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $frontendUser->start();
            $frontendUser->unpack_uc();

            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
                TypoScriptFrontendController::class,
                $context,
                $site,
                $siteLanguage,
                $pageArgs,
                $frontendUser
            );

            $GLOBALS['TSFE']->determineId();
            $GLOBALS['TSFE']->getFromCache();
            $GLOBALS['TSFE']->getConfigArray();
            $GLOBALS['TSFE']->newCObj();

            $validTSFE = true;
        } catch (Throwable $e) {
            self::$lastError = $e;
            unset($GLOBALS['TSFE']);
            if ($requestBackup) {
                $GLOBALS['TYPO3_REQUEST'] = $requestBackup;
            }
        }

        // we got our TSFE up and running, restore the PageRenderer
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRendererBackup);

        if ($validTSFE) {
            // calculate the absolute path prefix
            if (!empty($GLOBALS['TSFE']->config['config']['cliDomain'])) {
                $absRefPrefix = trim($GLOBALS['TSFE']->config['config']['cliDomain']);
                if ($absRefPrefix === 'auto') {
                    $GLOBALS['TSFE']->absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
                } else {
                    $GLOBALS['TSFE']->absRefPrefix = $absRefPrefix;
                }
            } else {
                $GLOBALS['TSFE']->absRefPrefix = '';
            }
        }

        return $validTSFE;
    }

    /**
     * The built UriBuilder behaves as if it was in FE mode
     *
     * @return UriBuilder|null
     */
    public static function getFrontendUriBuilder(): ?UriBuilder
    {
        if (class_exists(\TYPO3\CMS\Extbase\Mvc\Web\Request::class)) {
            $request = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Request::class);
            $request->setRequestUri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
            $request->setBaseUri(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
            try {
                $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                $uriBuilder->injectEnvironmentService(new EnvironmentService());
                $uriBuilder->setRequest($request);
            } catch (Exception $e) {
                return null;
            }
        } else {
            $request = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Request::class);
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $uriBuilder->setRequest($request);
        }
        return $uriBuilder;
    }
}
