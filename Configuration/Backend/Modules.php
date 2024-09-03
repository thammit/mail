<?php

use MEDIAESSENZ\Mail\Controller\QueueController;
use MEDIAESSENZ\Mail\Controller\RecipientController;
use MEDIAESSENZ\Mail\Controller\ReportController;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Controller\MailController;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Definitions for modules provided by EXT:examples
 */
try {
    $modulePosition = ConfigurationUtility::getExtensionConfiguration('mailModulePosition') ?? 'after:web';
} catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
    $modulePosition = 'after:web';
}
$modulePositionArray = explode(':', $modulePosition);
$navigationComponent = (new Typo3Version())->getMajorVersion() < 13 ? '@typo3/backend/page-tree/page-tree-element' : '@typo3/backend/tree/page-tree-element';

$config = [
    'mail' => [
        'position' => [$modulePositionArray[0] => $modulePositionArray[1]],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:mail/Resources/Private/Language/MainModule.xlf',
        'extensionName' => 'Mail',
        'iconIdentifier' => 'mail-module-main',
    ],
    'mail_mail' => [
        'parent' => 'mail',
        'position' => ['top'],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:mail/Resources/Private/Language/MailModule.xlf',
        'extensionName' => 'Mail',
        'iconIdentifier' => 'mail-module-mail',
        'navigationComponent' => $navigationComponent,
        'controllerActions' => [
            MailController::class => [
                'index',
                'updateConfiguration',
                'createMailFromInternalPage',
                'createMailFromExternalUrls',
                'createQuickMail',
                'draftMail',
                'updateContent',
                'settings',
                'categories',
                'updateCategories',
                'testMail',
                'sendTestMail',
                'sendTestMailToGroup',
                'scheduleSending',
                'finish',
                'delete',
                'noValidPageSelected',
            ],
        ],
    ],
    'mail_recipient' => [
        'parent' => 'mail',
        'position' => ['after' => 'mail_mail'],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:mail/Resources/Private/Language/RecipientModule.xlf',
        'extensionName' => 'Mail',
        'iconIdentifier' => 'mail-module-recipient',
        'navigationComponent' => $navigationComponent,
        'controllerActions' => [
            RecipientController::class => [
                'index',
                'show',
                'csvDownload',
                'csvImportWizard',
                'csvImportWizardUploadCsv',
                'csvImportWizardImportCsv',
                'csvImportWizardStepConfiguration',
                'csvImportWizardStepMapping',
                'csvImportWizardStepStartImport',
                'noValidPageSelected',
            ],
        ],
    ],
    'mail_report' => [
        'parent' => 'mail',
        'position' => ['after' => 'mail_recipient'],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:mail/Resources/Private/Language/ReportModule.xlf',
        'extensionName' => 'Mail',
        'iconIdentifier' => 'mail-module-report',
        'navigationComponent' => $navigationComponent,
        'controllerActions' => [
            ReportController::class => [
                'index',
                'show',
                'showTotalReturned',
                'disableTotalReturned',
                'csvExportTotalReturned',
                'showUnknown',
                'disableUnknown',
                'csvExportUnknown',
                'showFull',
                'disableFull',
                'csvExportFull',
                'showBadHost',
                'disableBadHost',
                'csvExportBadHost',
                'showBadHeader',
                'disableBadHeader',
                'csvExportBadHeader',
                'showReasonUnknown',
                'disableReasonUnknown',
                'csvExportReasonUnknown',
                'delete',
                'noValidPageSelected',
            ],
        ],
    ],
    'mail_queue' => [
        'parent' => 'mail',
        'position' => ['after' => 'mail_report'],
        'access' => 'user',
        'workspaces' => 'live',
        'labels' => 'LLL:EXT:mail/Resources/Private/Language/QueueModule.xlf',
        'extensionName' => 'Mail',
        'iconIdentifier' => 'mail-module-queue',
        'navigationComponent' => $navigationComponent,
        'controllerActions' => [
            QueueController::class => [
                'index',
                'saveConfiguration',
                'trigger',
                'delete',
                'pause',
                'continue',
                'noValidPageSelected',
            ],
        ],
    ],
];

try {
    if (!empty(ConfigurationUtility::getExtensionConfiguration('mailModulePageId')) || (int)ConfigurationUtility::getExtensionConfiguration('hideNavigation')) {
        unset($config['mail_mail']['navigationComponent']);
        unset($config['mail_recipient']['navigationComponent']);
        unset($config['mail_report']['navigationComponent']);
        unset($config['mail_queue']['navigationComponent']);
    }
} catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
}

return $config;
