<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FilterPageTreeMiddleware  implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $routePath = $request->getAttribute('route')->getPath();
        $refererIsMailModule = str_contains($normalizedParams->getHttpReferer(), '/module/MailMail/MailMail');
        if (
            (
                $routePath !== '/module/MailMail/MailMail' &&
                $routePath !== '/ajax/page/tree/fetchData' &&
                $refererIsMailModule
            ) ||
            (
                $routePath === '/module/MailMail/MailMail' &&
                !$refererIsMailModule
            )
        ) {
            // update page tree on next request using page renderer "render-preProcess" hook
            // see MEDIAESSENZ\Mail\Hooks\PageTreeRefresh->addJs
            $backendUser->setAndSaveSessionData('updatePageTree', true);
            return $handler->handle($request);
        }

        $queryParams = $request->getQueryParams();
        if ($routePath === '/ajax/page/tree/fetchData' && $refererIsMailModule) {
            $mailModulePageIds = $backendUser->getTSConfig()['tx_mail.']['mailModulePageIds'] ?? false;
            if ($mailModulePageIds) {
                $queryParams['alternativeEntryPoints'] = GeneralUtility::intExplode(',', $mailModulePageIds);
                $request = $request->withQueryParams($queryParams);
            }
        }
        return $handler->handle($request);
    }
}
