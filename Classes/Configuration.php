<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use MEDIAESSENZ\Mail\Controller\ConfigurationController;
use MEDIAESSENZ\Mail\Controller\ConfigurationControllerOld;
use MEDIAESSENZ\Mail\Controller\MailController;
use MEDIAESSENZ\Mail\Controller\MailControllerOld;
use MEDIAESSENZ\Mail\Controller\QueueController;
use MEDIAESSENZ\Mail\Controller\QueueControllerOld;
use MEDIAESSENZ\Mail\Controller\NavFrameController;
use MEDIAESSENZ\Mail\Controller\RecipientController;
use MEDIAESSENZ\Mail\Controller\RecipientListControllerOld;
use MEDIAESSENZ\Mail\Controller\ReportController;
use MEDIAESSENZ\Mail\Controller\StatisticsControllerOld;
use MEDIAESSENZ\Mail\Updates\DirectMailMigration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

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

    public static function addPageTSConfig(): void
    {
        // Category field disabled by default in backend forms.
        ExtensionManagementUtility::addPageTSConfig('
    	TCEFORM.tt_content.module_sys_dmail_category.disabled = 1
    	TCEFORM.tt_address.module_sys_dmail_category.disabled = 1
    	TCEFORM.fe_users.module_sys_dmail_category.disabled = 1
    	TCEFORM.sys_dmail_group.select_categories.disabled = 1
        ');
        ExtensionManagementUtility::addPageTSConfig('
    	TCEFORM.tt_content.module_mail_category.disabled = 1
    	TCEFORM.tt_address.module_mail_category.disabled = 1
    	TCEFORM.fe_users.module_mail_category.disabled = 1
    	TCEFORM.tx_mail_domain_model_group.categories.disabled = 1
        ');
    }

    public static function registerTranslations(): void
    {
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmailcat.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_Mail_Mail', 'EXT:mail/Resources/Private/Language/locallang_csh_DirectMail.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_Mail_RecipientList', 'EXT:mail/Resources/Private/Language/locallang_csh_RecipientList.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_Mail_Statistics', 'EXT:mail/Resources/Private/Language/locallang_csh_Statistics.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_Mail_Status', 'EXT:mail/Resources/Private/Language/locallang_csh_MailerEngine.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_Mail_Configuration', 'EXT:mail/Resources/Private/Language/locallang_csh_Configuration.xlf');
    }

    public static function registerBackendModules(): void
    {
        ExtensionManagementUtility::addModule(
            'Mail',
            '',
            '',
            '',
            [
                'access' => 'group,user',
                'name' => 'Mail',
                'iconIdentifier' => 'mail-module-main',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/MainModule.xlf',
                ],
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'mail',
            'top',
            [
                MailController::class => 'index,createMailFromInternalPage,createMailFromExternalUrls,createQuickMail,openMail,settings,categories,updateCategories,testMail,sendTestMail,scheduleSending,finish,delete,noPageSelected'
            ],
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-mail',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/MailModule.xlf',
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'recipient',
            'after:mail',
            [
                RecipientController::class => 'index,show,csvDownload,csvImportWizard,csvImportWizardStepConfiguration,csvImportWizardStepMapping,csvImportWizardStepStartImport'
            ],
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-recipient',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/RecipientModule.xlf',
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'report',
            'after:recipient',
            [
                ReportController::class => 'index,show,showTotalReturned,disableTotalReturned,csvExportTotalReturned,showUnknown,disableUnknown,csvExportUnknown,showFull,disableFull,csvExportFull,showBadHost,disableBadHost,csvExportBadHost,showBadHeader,disableBadHeader,csvExportBadHeader,showReasonUnknown,disableReasonUnknown,csvExportReasonUnknown'
            ],
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-report',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/StatisticModule.xlf',
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'queue',
            'after:report',
            [
                QueueController::class => 'index,trigger,delete'
            ],
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-queue',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/QueueModule.xlf',
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Mail',
            'Mail',
            'configuration',
            'after:queue',
            [
                ConfigurationController::class => 'index,update'
            ],
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'access' => 'group,user',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-configuration',
                'labels' => 'LLL:EXT:mail/Resources/Private/Language/ConfigurationModule.xlf',
            ]
        );

        $GLOBALS['TBE_STYLES']['skins']['mail']['stylesheetDirectories'][] = 'EXT:mail/Resources/Public/Css/';
    }

    public static function directMailMigration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['directMail2Mail']
            = DirectMailMigration::class;

    }
}
