<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationUtility
{

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function getDefaultScheme(): string
    {
        return self::getExtensionConfiguration('useHttpToFetch') ? 'http' : 'https';
    }

    /**
     * @param string $path
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getExtensionConfiguration(string $path = ''): string
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('mail', $path);
    }

    /**
     * @return array
     */
    public static function getDefaultRecipientSources(): array
    {
        $loader = GeneralUtility::makeInstance(YamlFileLoader::class);
        return $loader->load(GeneralUtility::getFileAbsFileName('EXT:mail/Configuration/Site/RecipientSources.yaml'))['mail']['recipientSources'] ?? [];
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function shouldFetchHtml(array $config): bool
    {
        return ($config['sendOptions'] & 2) !== 0;
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function shouldFetchPlainText(array $config): bool
    {
        return ($config['sendOptions'] & 1) !== 0;
    }

}
