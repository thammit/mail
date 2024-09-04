<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientSourceType;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
     * @param array $siteConfiguration
     * @return RecipientSourceConfigurationDTO[]
     * @throws Exception
     */
    public static function getRecipientSources(array $siteConfiguration = []): array
    {
        $recipientSources = $siteConfiguration['mail']['recipientSources'] ?? self::getDefaultRecipientSources() ?? [];

        if (array_key_exists('tt_address', $recipientSources) && !ExtensionManagementUtility::isLoaded('tt_address')) {
            unset($recipientSources['tt_address']);
        }

        $recipientSourcesWithDTOs = [];
        foreach ($recipientSources as $recipientSourceIdentifier => $recipientSourceConfiguration) {
            $recipientSourcesWithDTOs[$recipientSourceIdentifier] = new RecipientSourceConfigurationDTO($recipientSourceIdentifier, $recipientSourceConfiguration);
        }

        // get recipient sources from group records if type is plain and csv
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mail_domain_model_group');
        $csvRecipientSources = $queryBuilder
            ->select('uid', 'pid', 'title', 'type', 'csv_type')
            ->from('tx_mail_domain_model_group')
            ->where($queryBuilder->expr()->eq('type', RecipientGroupType::CSV))
            ->orWhere($queryBuilder->expr()->eq('type', RecipientGroupType::PLAIN))
            ->executeQuery()
            ->fetchAllAssociative();
        foreach ($csvRecipientSources as $csvRecipientSource) {
            $recipientSourceIdentifier = 'tx_mail_domain_model_group:' . $csvRecipientSource['uid'];
            $type = match ($csvRecipientSource['type']) {
                RecipientGroupType::CSV => match ($csvRecipientSource['csv_type']) {
                    CsvType::FILE => RecipientSourceType::CSVFILE,
                    default => RecipientSourceType::CSV,
                },
                default => RecipientSourceType::PLAIN,
            };
            $recipientSourceConfiguration = [
                'table' => 'tx_mail_domain_model_group',
                'type' => $type,
                'title' => $csvRecipientSource['title'],
                'pid' => $csvRecipientSource['pid'],
                'groupUid' => $csvRecipientSource['uid'],
                'icon' => 'mail-group',
            ];
            $recipientSourcesWithDTOs[$recipientSourceIdentifier] = new RecipientSourceConfigurationDTO($recipientSourceIdentifier, $recipientSourceConfiguration);
        }

        return $recipientSourcesWithDTOs;
    }

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
