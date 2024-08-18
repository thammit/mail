<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Middleware;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class FilterPageTreeMiddleware  implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Doctrine\DBAL\Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        if ($pageId = $request->getQueryParams()['id'] ?? false) {
            if (MathUtility::canBeInterpretedAsInteger($pageId)) {
                $backendUser->setAndSaveSessionData('lastSelectedPage', (int)$pageId);
            }
        }

        $mailModulePageIds = ConfigurationUtility::getExtensionConfiguration('mailModulePageIds');
        if ($backendUser->getTSConfig()['tx_mail.']['mailModulePageIds'] ?? false) {
            $mailModulePageIds = $backendUser->getTSConfig()['tx_mail.']['mailModulePageIds'] ?? false;
        }

        if ($mailModulePageIds) {
            /** @var NormalizedParams $normalizedParams */
            $normalizedParams = $request->getAttribute('normalizedParams');
            $identifier = (new Typo3Version())->getMajorVersion() < 12 ? '/module/MailMail/Mail' : '/module/mail/';
            $refererIsMailModule = str_contains($normalizedParams->getHttpReferer(), $identifier);
            $routePath = $request->getAttribute('route')->getPath();
            $requestIsMailModule = str_starts_with($routePath, $identifier);
            $requestIsPageTree = $routePath === '/ajax/page/tree/fetchData';

            if ($refererIsMailModule && $requestIsPageTree) {
                $queryParams = $request->getQueryParams();
                if (trim($mailModulePageIds) === 'auto') {
                    $queryParams['alternativeEntryPoints'] = GeneralUtility::makeInstance(PagesRepository::class)->findMailModulePageUids();
                } else {
                    $queryParams['alternativeEntryPoints'] = GeneralUtility::intExplode(',', $mailModulePageIds);
                }
                $request = $request->withQueryParams($queryParams);
            }

            if (
                (!$requestIsMailModule && $refererIsMailModule && !$requestIsPageTree) ||
                (!$refererIsMailModule && $requestIsMailModule)
            ) {
                // update page tree on next request using page renderer "render-preProcess" hook (TYPO3 11) or events (TYPO3 12)
                // see MEDIAESSENZ\Mail\Hooks\PageTreeRefresh->addJs (TYPO3 11)
                // see MEDIAESSENZ\Mail\EventListener\PageTreeRefresh and MEDIAESSENZ\Mail\EventListener\AssetRenderer\PageTreeRefresh (TYPO3 12)
                $backendUser->setAndSaveSessionData('updatePageTree', true);
            }
        }
        return $handler->handle($request);
    }
}
