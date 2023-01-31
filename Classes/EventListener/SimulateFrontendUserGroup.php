<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Utility\RegistryUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

class SimulateFrontendUserGroup
{
    public function __invoke(ModifyResolvedFrontendGroupsEvent $event): void
    {
        if (GeneralUtility::_GET('mail_fe_group') && GeneralUtility::_GET('access_token')) {
            $frontendUserGroup = (int)GeneralUtility::_GET('mail_fe_group');
            if ($frontendUserGroup > 0 && RegistryUtility::validateAndRemoveAccessToken(GeneralUtility::_GET('access_token'))) {
                $event->setGroups([['uid' => $frontendUserGroup]]);
            }
        }
    }
}
