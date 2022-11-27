<?php
return [
    'ctrl' => [
        'label' => 'title',
        'default_sortby' => 'ORDER BY title',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group',
        'delete' => 'deleted',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::PAGES => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::CSV => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::STATIC => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::QUERY => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::OTHER => 'mail-group',
            \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::MODEL => 'mail-group',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.description',
            'config' => [
                'type' => 'text',
                'cols' => '40',
                'rows' => '3',
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.0', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::PAGES],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.1', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::CSV],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.2', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::STATIC],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.4', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::OTHER],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.5', \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::MODEL],
                ],
                'default' => '0',
            ],
        ],
        'static_list' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.static_list',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_address,fe_users,fe_groups',
                'MM' => 'tx_mail_group_mm',
                'size' => '20',
                'maxitems' => '100000',
                'minitems' => '0',
            ],
        ],
        'pages' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
            ],
        ],
        'children' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.mail_groups',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_mail_domain_model_group',
                'size' => '3',
                'minitems' => '0',
                'maxitems' => '22',
            ],
        ],
        'recursive' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.recursive',
            'config' => [
                'type' => 'check',
            ],
        ],
        'record_type' => [
            'label' => 'Record Type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'allowNonIdValues' => true,
                'items' => [
                    ['', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.0', \MEDIAESSENZ\Mail\Domain\Model\Address::class, 'EXT:tt_address/Resources/Public/Icons/tt_address.svg'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.1', \MEDIAESSENZ\Mail\Domain\Model\FrontendUser::class, 'status-user-frontend'],
                ],
                'minitems' => 1,
            ],
        ],
        'record_types' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.0', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.1', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.2', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.record_types.I.3', ''],
                ],
                'cols' => 2,
                'default' => 1,
            ],
        ],
        'list' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.list',
            'config' => [
                'type' => 'text',
                'cols' => '48',
                'rows' => '10',
            ],
        ],
        'csv' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.0', '0'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.1', '1'],
                ],
                'default' => '0',
            ],
        ],
        'categories' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.select_categories',
            'config' => [
                'type' => 'category',
            ],
        ],
    ],
    'types' => [
        \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::PAGES => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,record_types,pages,recursive,categories'],
        \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::CSV => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,list,csv'],
        \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::STATIC => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,static_list'],
        \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::OTHER => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,children'],
        \MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType::MODEL => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,record_type,pages,recursive,categories'],
    ],
];
