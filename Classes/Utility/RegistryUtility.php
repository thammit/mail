<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RegistryUtility
{
    /**
     * Create an access token and save it in the Registry
     *
     * @return string
     */
    public static function createAndGetAccessToken(): string
    {
        /* @var Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $accessToken = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(32);
        $registry->set('tx_directmail', 'accessToken', $accessToken);

        return $accessToken;
    }

    /**
     * Create an access token and save it in the Registry
     *
     * @param string $accessToken The access token to validate
     *
     * @return bool
     */
    public static function validateAndRemoveAccessToken(string $accessToken): bool
    {
        /* @var Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $registeredAccessToken = $registry->get('tx_directmail', 'accessToken');
        $registry->remove('tx_directmail', 'accessToken');

        return !empty($registeredAccessToken) && $registeredAccessToken === $accessToken;
    }
}
