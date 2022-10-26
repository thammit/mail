<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

/**
 * This class is used to create an extbase UriBuilder, which is constantly in Frontend Mode.
 * Only needed in Core v10
 */
class EnvironmentService extends \TYPO3\CMS\Extbase\Service\EnvironmentService
{
    public function isEnvironmentInBackendMode(): bool
    {
        return false;
    }
}
