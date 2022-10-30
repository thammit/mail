<?php
defined('TYPO3') or die();

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5',
    \MEDIAESSENZ\Mail\Constants::MAIL_MODULE_NAME,
    'mail-module',
];

if (!is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'] = [];
}

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-' . \MEDIAESSENZ\Mail\Constants::MAIL_MODULE_NAME] = 'app-pagetree-folder-contains-mail';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'mail',
    'Configuration/TsConfig/Page/ContentElement/All.tsconfig',
    'Mail: Remove not supported content elements'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'mail',
    'Configuration/TsConfig/Page/BackendLayouts/Mail.tsconfig',
    'Mail: Add simple mail backend layout'
);
