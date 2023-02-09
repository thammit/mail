<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Middleware;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class FilterPageTreeMiddleware  implements MiddlewareInterface
{
    /**
     * @throws DBALException
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        if (array_key_exists('id', $request->getQueryParams())) {
            $pageId = $request->getQueryParams()['id'];
            if (MathUtility::canBeInterpretedAsInteger($pageId)) {
                $backendUser->setAndSaveSessionData('lastSelectedPage', (int)$pageId);
            }
        }

        $mailModulePageIds = $backendUser->getTSConfig()['tx_mail.']['mailModulePageIds'] ?? false;

        if ($mailModulePageIds) {
            /** @var NormalizedParams $normalizedParams */
            $normalizedParams = $request->getAttribute('normalizedParams');
            $refererIsMailModule = str_contains($normalizedParams->getHttpReferer(), '/module/MailMail/Mail');
            $routePath = $request->getAttribute('route')->getPath();
            $requestIsMailModule = str_starts_with($routePath, '/module/MailMail/Mail');
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
                // update page tree on next request using page renderer "render-preProcess" hook
                // see MEDIAESSENZ\Mail\Hooks\PageTreeRefresh->addJs
                $backendUser->setAndSaveSessionData('updatePageTree', true);
            }
        }
        return $handler->handle($request);
    }
}
