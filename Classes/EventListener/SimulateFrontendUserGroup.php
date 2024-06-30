<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Utility\RegistryUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

class SimulateFrontendUserGroup
{
    public function __invoke(ModifyResolvedFrontendGroupsEvent $event): void
    {
        $queryParams = $event->getRequest()->getQueryParams();
        if ($mailFeGroup = $queryParams['mail_fe_group'] ?? false && $accessToken = $queryParams['access_token'] ?? false) {
            $frontendUserGroup = (int)$mailFeGroup;
            if ($frontendUserGroup > 0 && RegistryUtility::validateAndRemoveAccessToken($accessToken)) {
                $event->setGroups([['uid' => $frontendUserGroup]]);
            }
        }
    }
}
