<?php

use MEDIAESSENZ\Mail\Type\Enumeration\CsvSeparator;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\UserFunctions\RecipientSourcesProcFunc;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$return = [
    'ctrl' => [
        'label' => 'title',
        'default_sortby' => 'title',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'mail-group',
            RecipientGroupType::PAGES => 'mail-group',
            RecipientGroupType::CSV => 'mail-group',
            RecipientGroupType::PLAIN => 'mail-group',
            RecipientGroupType::STATIC => 'mail-group',
            RecipientGroupType::OTHER => 'mail-group',
        ],
    ],
    'types' => [
        RecipientGroupType::PAGES => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,recipient_sources,pages,recursive,categories'],
        RecipientGroupType::PLAIN => [
            'showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,list,mail_html,categories',
            'columnsOverrides' => [
                'categories' => [
                    'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.selectCategoriesForPlainCsv',
                ]
            ]
        ],
        RecipientGroupType::CSV => [
            'showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,csv_type,csv_separator,csv_data,csv_file,mail_html,categories',
            'columnsOverrides' => [
                'categories' => [
                    'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.selectCategoriesForPlainCsv',
                ]
            ]
        ],
        RecipientGroupType::STATIC => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,static_list'],
        RecipientGroupType::OTHER => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,children'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 120,
                'eval' => 'trim,required',
                'default' => ''
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.pages',
                        RecipientGroupType::PAGES
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.list',
                        RecipientGroupType::PLAIN
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.csv',
                        RecipientGroupType::CSV
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.static',
                        RecipientGroupType::STATIC
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.other',
                        RecipientGroupType::OTHER
                    ],
                ],
                'default' => RecipientGroupType::PAGES,
            ],
        ],
        'static_list' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.staticList',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users,fe_groups',
                'MM' => 'tx_mail_group_mm',
                'size' => 20,
                'maxitems' => 100000,
                'minitems' => 0,
            ],
        ],
        'pages' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => 3,
                'maxitems' => 22,
                'minitems' => 0,
            ],
        ],
        'children' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.mailGroups',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_mail_domain_model_group',
                'size' => 3,
                'minitems' => 0,
                'maxitems' => 22,
            ],
        ],
        'recursive' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.recursive',
            'config' => [
                'type' => 'check',
            ],
        ],
        'recipient_sources' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.recipientSources',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'allowNonIdValues' => true,
                'itemsProcFunc' => RecipientSourcesProcFunc::class . '->itemsProcFunc',
            ],
        ],
        'list' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.list',
            'description' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.list.description',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 10,
                'fixedFont' => true,
            ],
        ],
        'csv_type' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvType',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvType.I.0',
                        CsvType::PLAIN
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvType.I.1',
                        CsvType::FILE
                    ],
                ],
                'default' => CsvType::PLAIN,
            ],
        ],
        'csv_separator' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvSeparator',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvSeparator.I.0',
                        CsvSeparator::COMMA
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvSeparator.I.1',
                        CsvSeparator::SEMICOLON
                    ],
                    [
                        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvSeparator.I.2',
                        CsvSeparator::TAB
                    ],
                ],
                'default' => CsvSeparator::COMMA,
            ],
        ],
        'csv_data' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvData',
            'description' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvData.description',
            'displayCond' => 'FIELD:csv_type:=:' . CsvType::PLAIN,
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 10,
                'enableTabulator' => true,
                'fixedFont' => true,
            ],
        ],
        'csv_file' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvFile',
            'description' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csvData.description',
            'displayCond' => 'FIELD:csv_type:=:' . CsvType::FILE,
            'config' => [
                'type' => 'file',
                'allowed' => 'csv,txt',
                'maxitems' => 1,
                'minitems' => 1,
            ],
        ],
        'mail_html' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_html',
            'exclude' => '1',
            'config' => [
                'type' => 'check',
                'default' => 1
            ]
        ],
        'categories' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.selectCategories',
            'config' => [
                'type' => 'category',
            ],
        ],
    ],
];

if ((int)\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version() < 12) {
    $return['columns']['csv_file']['config'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
        'csv_file',
        [
            'maxitems' => 1,
            'minitems' => 1,
        ],
        'csv,txt'
    );
}

return $return;