<?php

use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\UserFunctions\RecipientSourcesProcFunc;

return [
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
            RecipientGroupType::STATIC => 'mail-group',
            RecipientGroupType::QUERY => 'mail-group',
            RecipientGroupType::OTHER => 'mail-group',
        ],
    ],
    'types' => [
        RecipientGroupType::PAGES => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,recipient_sources,pages,recursive,categories'],
        RecipientGroupType::CSV => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,list,csv,mail_html,categories'],
        RecipientGroupType::STATIC => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,static_list'],
        //\MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::QUERY => ['showitem' => 'type, hidden, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.advanced,static_list'],
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
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.I.0', RecipientGroupType::PAGES],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.I.1', RecipientGroupType::CSV],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.I.2', RecipientGroupType::STATIC],
                    //['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.I.3', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::QUERY],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.type.I.4', RecipientGroupType::OTHER],
                ],
                'default' => '0',
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
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 10,
            ],
        ],
        'csv' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csv',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csv.I.0', '0'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_group.csv.I.1', '1'],
                ],
                'default' => '0',
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
