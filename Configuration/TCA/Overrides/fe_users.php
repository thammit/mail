<?php
defined('TYPO3') or die();

// fe_users modified
$feUsersCols = [
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
    'mail_salutation' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_salutation',
        'exclude' => '1',
        'config' => [
            'type' => 'input'
        ]
    ],
    'categories' => [
        'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_category.categories',
        'exclude' => true,
        'config' => [
            'type' => 'category'
        ]
    ],
    'tstamp' => [
        'label' => 'Last modified',
        'config' => [
            'type' => 'passthrough'
        ]
    ],
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $feUsersCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users', '--div--;Mail,mail_active,mail_html,mail_salutation,categories');
