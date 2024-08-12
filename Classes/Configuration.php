<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use Fetch\Server;
use MEDIAESSENZ\Mail\ContentObject\EmogrifierContentObject;
use MEDIAESSENZ\Mail\ContentObject\ScssContentObject;
use MEDIAESSENZ\Mail\Controller\MailController;
use MEDIAESSENZ\Mail\Controller\QueueController;
use MEDIAESSENZ\Mail\Controller\RecipientController;
use MEDIAESSENZ\Mail\Controller\ReportController;
use MEDIAESSENZ\Mail\DependencyInjection\EventListenerCompilerPass;
use MEDIAESSENZ\Mail\Hooks\AddBackToMailWizardButton;
use MEDIAESSENZ\Mail\Hooks\PageTreeRefresh;
use MEDIAESSENZ\Mail\Property\TypeConverter\DateTimeImmutableConverter;
use MEDIAESSENZ\Mail\Updates\AddStatus;
use MEDIAESSENZ\Mail\Updates\CsvGroupConverter;
use MEDIAESSENZ\Mail\Updates\DirectMailMigration;
use MEDIAESSENZ\Mail\Updates\ImprovedProcessHandlingUpdater;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DependencyInjection\Initialization\ContainerInitialization;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

final class Configuration
{
    public static function registerFluidNameSpace(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['mail'] = ['MEDIAESSENZ\Mail\ViewHelpers'];
    }

    public static function addModuleTypoScript(): void
    {
        ExtensionManagementUtility::addTypoScriptSetup('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:mail/Configuration/TypoScript/Backend/setup.typoscript">');
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function addPageTSConfig(): void
    {
        if (ConfigurationUtility::getExtensionConfiguration('deactivateCategories') ?? false) {
            // Category field disabled by default in backend forms.
            ExtensionManagementUtility::addPageTSConfig('
            TCEFORM.tt_content.categories.disabled = 1
            TCEFORM.tt_address.categories.disabled = 1
            TCEFORM.fe_users.categories.disabled = 1
            TCEFORM.tx_mail_domain_model_group.categories.disabled = 1
            ');
        }
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function addUserTSConfig(): void
    {
        ExtensionManagementUtility::addUserTSConfig('
        options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . (int)(ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') ?? Constants::DEFAULT_MAIL_PAGE_TYPE) . ')
        ');
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function registerBackendModules(): void
    {
        $navigationComponentId = 'TYPO3/CMS/Backend/PageTree/PageTreeElement';
        try {
            if (!empty(ConfigurationUtility::getExtensionConfiguration('mailModulePageId')) || ConfigurationUtility::getExtensionConfiguration('hideNavigation')) {
                $navigationComponentId = '';
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
        }
        $modulePosition = ConfigurationUtility::getExtensionConfiguration('mailModulePosition') ?? 'after:web';
        ExtensionUtility::registerModule(
            'Mail',
            'mail',
            '',
            $modulePosition,
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
                MailController::class => 'index,updateConfiguration,createMailFromInternalPage,createMailFromExternalUrls,createQuickMail,draftMail,updateContent,settings,categories,updateCategories,testMail,sendTestMail,scheduleSending,finish,delete,noValidPageSelected',
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
                RecipientController::class => 'index,show,csvDownload,csvImportWizard,csvImportWizardUploadCsv,csvImportWizardImportCsv,csvImportWizardStepConfiguration,csvImportWizardStepMapping,csvImportWizardStepStartImport,noValidPageSelected',
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
                ReportController::class => 'index,show,showTotalReturned,disableTotalReturned,csvExportTotalReturned,showUnknown,disableUnknown,csvExportUnknown,showFull,disableFull,csvExportFull,showBadHost,disableBadHost,csvExportBadHost,showBadHeader,disableBadHeader,csvExportBadHeader,showReasonUnknown,disableReasonUnknown,csvExportReasonUnknown,delete,noValidPageSelected',
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
                QueueController::class => 'index,saveConfiguration,trigger,delete,noValidPageSelected',
            ],
            [
                'navigationComponentId' => $navigationComponentId,
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-queue',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/QueueModule.xlf',
            ]
        );
    }

    public static function addMailStyleSheetDirectory(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $GLOBALS['TBE_STYLES']['skins']['mail']['stylesheetDirectories'][] = 'EXT:mail/Resources/Public/Css/Backend';
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['mail'] = 'EXT:mail/Resources/Public/Css/Backend';
        }
    }

    public static function registerHooks(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['MEDIAESSENZ/Mail'] = PageTreeRefresh::class . '->addJs';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/db_layout.php']['drawHeaderHook']['MEDIAESSENZ/Mail'] = PageTreeRefresh::class . '->addHeaderJs';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/db_layout.php']['drawHeaderHook']['MEDIAESSENZ/MailWizardBackButton'] = AddBackToMailWizardButton::class . '->render';
    }

    public static function addTypoScriptContentObject(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] ?? [], [
            'EMOGRIFIER' => EmogrifierContentObject::class,
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] ?? [], [
            'SCSS' => ScssContentObject::class,
        ]);
    }

    public static function registerMigrations(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['mailDirectMailMigration'] = DirectMailMigration::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['mailImproveProcessHandlingUpdater'] = ImprovedProcessHandlingUpdater::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['mailCsvGroupConverter'] = CsvGroupConverter::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['mailAddStatus'] = AddStatus::class;
    }

    public static function registerTypeConverter(): void
    {
        ExtensionUtility::registerTypeConverter(DateTimeImmutableConverter::class);
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

    public static function excludeMailParamsFromCHashCalculation(): void
    {
        if ((int)($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['enforceValidation'] ?? 0) === 1) {
            ArrayUtility::mergeRecursiveWithOverrule($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'], ['mail','rid','aC','juHash','jumpurl']);
        }
    }

    public static function includeRequiredLibrariesForNoneComposerMode(): void
    {
        if (!class_exists(Server::class) && !Environment::isComposerMode()) {
            // @phpstan-ignore-next-line
            @include 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
        }
    }
}
