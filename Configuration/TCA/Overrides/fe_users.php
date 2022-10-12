<?php
defined('TYPO3') or die();

// fe_users modified
$feUsersCols = [
    'newsletter' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.newsletter',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'categories' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.category',
        'exclude' => true,
        'config' => [
            'type' => 'category'
        ]
    ],
    'accepts_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.htmlemail',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $feUsersCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users', '--div--;Mail,newsletter,accepts_html,categories');
