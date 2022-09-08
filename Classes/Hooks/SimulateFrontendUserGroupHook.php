<?php

namespace MEDIAESSENZ\Mail\Hooks;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use MEDIAESSENZ\Mail\Utility\RegistryUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Hooks which is called while FE rendering
 *
 * Class TypoScriptFrontendController
 * @package MEDIAESSENZ\Mail\Hooks
 */
class SimulateFrontendUserGroupHook
{

    /**
     * If a backend user is logged in and
     * a frontend usergroup is specified in the GET parameters, use this
     * group to simulate access to access protected page with content to be sent
     *
     * @param $parameters
     * @param TypoScriptFrontendController $typoScriptFrontendController
     *
     * @return void
     */
    public function __invoke($parameters, TypoScriptFrontendController $typoScriptFrontendController): void
    {
        $directMailFeGroup = (int)GeneralUtility::_GET('dmail_fe_group');
        $accessToken = (string)GeneralUtility::_GET('access_token');
        if ($directMailFeGroup > 0 && GeneralUtility::makeInstance(RegistryUtility::class)->validateAndRemoveAccessToken($accessToken)) {
            if ($typoScriptFrontendController->fe_user->user) {
                $typoScriptFrontendController->fe_user->user[$typoScriptFrontendController->usergroup_column] = $directMailFeGroup;
            } else {
                $typoScriptFrontendController->fe_user->user = [
                    $typoScriptFrontendController->fe_user->usergroup_column => $directMailFeGroup,
                ];
            }
        }
    }
}
