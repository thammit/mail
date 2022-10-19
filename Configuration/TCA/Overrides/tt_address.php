<?php
defined('TYPO3') or die();

// tt_address modified
$ttAddressCols = [
    'accepts_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:accepts_html',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_address', $ttAddressCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('tt_address', '--div--;Mail,accepts_html');
