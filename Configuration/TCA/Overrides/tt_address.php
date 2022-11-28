<?php
defined('TYPO3') or die();

// tt_address modified
$ttAddressCols = [
    'mail_active' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_active',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'mail_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_html',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_address', $ttAddressCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('tt_address', '--div--;Mail,mail_active,mail_html');
