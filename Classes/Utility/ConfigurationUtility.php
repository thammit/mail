<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;

class ConfigurationUtility
{

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function getDefaultScheme(): string
    {
        return self::getExtensionConfiguration('UseHttpToFetch') ? 'http' : 'https';
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

    /**
     * Get the configured charset.
     *
     * This method used to initialize the TSFE object to get the charset on a per-page basis. Now it just evaluates the
     * configured charset of the instance
     *
     * @return string
     * @throws InvalidConfigurationTypeException
     */
    public static function getCharacterSet(): string
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $characterSet = 'utf-8';

        if (isset($settings['config.']['metaCharset'])) {
            $characterSet = $settings['config.']['metaCharset'];
        } else {
            if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])) {
                $characterSet = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
            }
        }

        return mb_strtolower($characterSet);
    }
}
