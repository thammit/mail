<?php
defined('TYPO3') or die();

// tt_content modified
$ttContentCols = [
    'module_sys_dmail_category' => [
        'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_category.category',
        'exclude' => false,
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'foreign_table' => 'sys_dmail_category',
            'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
            'MM' => 'sys_dmail_ttcontent_category_mm',
            'itemsProcFunc' => \MEDIAESSENZ\Mail\Utility\TcaUtility::class . '->getLocalizedCategories',
            'itemsProcFunc_config' => [
                'table' => 'sys_dmail_category',
                'indexField' => 'uid',
            ],
        ],
    ],
];
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $ttContentCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('tt_content', '--div--;Direct mail,module_sys_dmail_category');
