<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use MEDIAESSENZ\Mail\Controller\ConfigurationController;
use MEDIAESSENZ\Mail\Controller\DmailController;
use MEDIAESSENZ\Mail\Controller\MailerEngineController;
use MEDIAESSENZ\Mail\Controller\NavFrameController;
use MEDIAESSENZ\Mail\Controller\RecipientListController;
use MEDIAESSENZ\Mail\Controller\StatisticsController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final class Configuration
{
    public static function registerHooks(): void
    {
        // Register hook for simulating a user group
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['determineId-PreProcessing']['mail'] = \MEDIAESSENZ\Mail\Hooks\SimulateFrontendUserGroupHook::class;
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
    }

    public static function registerTranslations(): void
    {
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category', 'EXT:mail/Resources/Private/Language/locallang_csh_sysdmailcat.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_DirectMail', 'EXT:mail/Resources/Private/Language/locallang_csh_DirectMail.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_RecipientList', 'EXT:mail/Resources/Private/Language/locallang_csh_RecipientList.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Statistics', 'EXT:mail/Resources/Private/Language/locallang_csh_Statistics.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_MailerEngine', 'EXT:mail/Resources/Private/Language/locallang_csh_MailerEngine.xlf');
        ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Configuration', 'EXT:mail/Resources/Private/Language/locallang_csh_Configuration.xlf');
    }

    public static function registerBackendModules(): void
    {
        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            '',
            '',
            '',
            [
                'routeTarget' => NavFrameController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame',
                'iconIdentifier' => 'mail-module-group',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangNavFrame.xlf',
                ],
            ]
        );

        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            'Mail',
            'bottom',
            '',
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => DmailController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame_Mail',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-start',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangDirectMail.xlf',
                ],
            ]
        );

        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            'RecipientList',
            'bottom',
            '',
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => RecipientListController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame_RecipientList',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-recipient-list',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangRecipientList.xlf',
                ],
            ]
        );

        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            'Statistics',
            'bottom',
            '',
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => StatisticsController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame_Statistics',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-statistics',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangStatistics.xlf',
                ],
            ]
        );

        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            'Status',
            'bottom',
            '',
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => MailerEngineController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame_Status',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-status',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangMailerEngine.xlf',
                ],
            ]
        );

        ExtensionManagementUtility::addModule(
            'MailNavFrame',
            'Configuration',
            'bottom',
            '',
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => ConfigurationController::class . '::indexAction',
                'access' => 'group,user',
                'name' => 'MailNavFrame_Configuration',
                'workspaces' => 'online',
                'iconIdentifier' => 'mail-module-configuration',
                'labels' => [
                    'll_ref' => 'LLL:EXT:mail/Resources/Private/Language/locallangConfiguration.xlf',
                ],
            ]
        );

        $GLOBALS['TBE_STYLES']['skins']['direct_mail']['stylesheetDirectories'][] = 'EXT:mail/Resources/Public/Css/';
    }
}
