<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Middleware;

use MEDIAESSENZ\Mail\Utility\RegistryUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class SimulateFrontendUserGroupMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (GeneralUtility::_GET('mail_fe_group') && GeneralUtility::_GET('access_token')) {
            $frontendUserGroup = (int)GeneralUtility::_GET('mail_fe_group');
            $accessToken = GeneralUtility::_GET('access_token');
            if ($frontendUserGroup > 0 && RegistryUtility::validateAndRemoveAccessToken($accessToken)) {
                /** @var FrontendUserAuthentication $feUser */
                $frontendUserAuthentication = $request->getAttribute('frontend.user');
                if ($frontendUserAuthentication->user) {
                    $frontendUserAuthentication->user[$frontendUserAuthentication->usergroup_column] = $frontendUserGroup;
                } else {
                    $frontendUserAuthentication->user = [
                        $frontendUserAuthentication->usergroup_column => $frontendUserGroup,
                    ];
                }
            }
        }

        return $handler->handle($request);
    }
}
