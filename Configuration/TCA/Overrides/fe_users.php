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
    'accepts_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.htmlemail',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'module_sys_dmail_newsletter' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.newsletter',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ],
    'module_sys_dmail_category' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.category',
        'exclude' => '1',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'renderMode' => 'checkbox',
            'foreign_table' => 'sys_dmail_category',
            'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
            'itemsProcFunc' => \MEDIAESSENZ\Mail\Utility\TcaUtility::class . '->getLocalizedCategories',
            'itemsProcFunc_config' => [
                'table' => 'sys_dmail_category',
                'indexField' => 'uid',
            ],
            'size' => 5,
            'minitems' => 0,
            'maxitems' => 60,
            'MM' => 'sys_dmail_feuser_category_mm',
        ]
    ],
    'module_sys_dmail_html' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.htmlemail',
        'exclude' => '1',
        'config' => [
            'type' => 'check'
        ]
    ]
];

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $feUsersCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users', '--div--;Direct mail,newsletter,module_sys_dmail_newsletter,module_sys_dmail_category,accepts_html,module_sys_dmail_html');
