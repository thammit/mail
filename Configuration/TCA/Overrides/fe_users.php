<?php
defined('TYPO3') or die();

// fe_users modified
$feUsersCols = [
    'newsletter' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:newsletter',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'accepts_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:accepts_html',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'categories' => [
        'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_category.categories',
        'exclude' => true,
        'config' => [
            'type' => 'category'
        ]
    ],
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $feUsersCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users', '--div--;Mail,newsletter,accepts_html,categories');
