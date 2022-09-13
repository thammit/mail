<?php
defined('TYPO3') or die();

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5',
    'dmail',
    'mail-module',
];

if (!is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'] = [];
}

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-dmail'] = 'app-pagetree-folder-contains-mails';
