<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use MEDIAESSENZ\Mail\Controller\ConfigurationController;
use MEDIAESSENZ\Mail\Controller\MailController;
use MEDIAESSENZ\Mail\Controller\QueueController;
use MEDIAESSENZ\Mail\Controller\RecipientController;
use MEDIAESSENZ\Mail\Controller\ReportController;
use MEDIAESSENZ\Mail\Property\TypeConverter\DateTimeImmutableConverter;
use MEDIAESSENZ\Mail\Updates\DirectMailMigration;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

final class Configuration
{
    public static function registerHooks(): void
    {
        // Register hook for simulating a user group -> now SimulateFrontendUserGroupMiddleware
        // $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['determineId-PreProcessing']['mail'] = \MEDIAESSENZ\Mail\Hooks\SimulateFrontendUserGroupHook::class . '->__invoke';
    }

    public static function registerFluidNameSpace(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['mail'] = ['MEDIAESSENZ\Mail\ViewHelpers'];
    }

    public static function addModuleTypoScript(): void
    {
        ExtensionManagementUtility::addTypoScriptSetup('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:mail/Configuration/TypoScript/Backend/setup.typoscript">');
    }

    public static function addPageTSConfig(): void
    {
        // Category field disabled by default in backend forms.
        ExtensionManagementUtility::addPageTSConfig('
    	TCEFORM.tt_content.categories.disabled = 1
    	TCEFORM.tt_address.categories.disabled = 1
    	TCEFORM.fe_users.categories.disabled = 1
    	TCEFORM.tx_mail_domain_model_group.categories.disabled = 1
        ');
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function addUserTSConfig(): void
    {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
        options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . (int)(ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') ?? Constants::DEFAULT_MAIL_PAGE_TYPE) . ')
        ');
    }

    public static function registerBackendModules(): void
    {
        $navigationComponentId = 'TYPO3/CMS/Backend/PageTree/PageTreeElement';
        try {
            if (!empty(ConfigurationUtility::getExtensionConfiguration('mailModulePageId'))) {
                $navigationComponentId = '';
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {

        }
        ExtensionUtility::registerModule(
            'Mail',
            'mail',
            '',
            '',
            [],
            [
                'access' => 'group,user',
                'name' => 'Mail',
                'iconIdentifier' => 'mail-module-main',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/MainModule.xlf',
                ],
            ],
        );

        ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'mail',
            'top',
            [
                MailController::class => 'index,createMailFromInternalPage,createMailFromExternalUrls,createQuickMail,openMail,settings,categories,updateCategories,testMail,sendTestMail,scheduleSending,finish,delete,noPageSelected'
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-mail',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/MailModule.xlf',
            ]
        );

        ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'recipient',
            'after:mail',
            [
                RecipientController::class => 'index,show,csvDownload,csvImportWizard,csvImportWizardStepConfiguration,csvImportWizardStepMapping,csvImportWizardStepStartImport'
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-recipient',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/RecipientModule.xlf',
            ]
        );

        ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'report',
            'after:recipient',
            [
                ReportController::class => 'index,show,showTotalReturned,disableTotalReturned,csvExportTotalReturned,showUnknown,disableUnknown,csvExportUnknown,showFull,disableFull,csvExportFull,showBadHost,disableBadHost,csvExportBadHost,showBadHeader,disableBadHeader,csvExportBadHeader,showReasonUnknown,disableReasonUnknown,csvExportReasonUnknown'
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-report',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/ReportModule.xlf',
            ]
        );

        ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'queue',
            'after:report',
            [
                QueueController::class => 'index,trigger,delete'
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-queue',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/QueueModule.xlf',
            ]
        );

        ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'configuration',
            'after:queue',
            [
                ConfigurationController::class => 'index,update'
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-configuration',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/ConfigurationModule.xlf',
            ]
        );

        $GLOBALS['TBE_STYLES']['skins']['mail']['stylesheetDirectories'][] = 'EXT:mail/Resources/Public/Css/Backend';
    }

    public static function addTypoScripContentObject(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'EMOGRIFIER' => \MEDIAESSENZ\Mail\ContentObject\EmogrifierContentObject::class
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'SCSS' => \MEDIAESSENZ\Mail\ContentObject\ScssContentObject::class
        ]);
    }

    public static function directMailMigration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['directMail2Mail'] = DirectMailMigration::class;
    }

    public static function registerTypeConverter(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12)
        {
            ExtensionUtility::registerTypeConverter(DateTimeImmutableConverter::class);
        }
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function addMailPageType(): void
    {
        $GLOBALS['PAGES_TYPES'][(int)(ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') ?? Constants::DEFAULT_MAIL_PAGE_TYPE)] = [
            'type' => 'web',
            'allowedTables' => '*',
        ];
    }
}
