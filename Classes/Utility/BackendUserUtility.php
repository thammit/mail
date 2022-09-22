<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class BackendUserUtility
{

    public static function backendUserPermissions(): string
    {
        return self::getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
    }

    public static function isAdmin(): bool
    {
        return self::getBackendUser()->isAdmin();
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
