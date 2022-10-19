<?php
return [
    'ctrl' => [
        'label' => 'subject',
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail',
        'delete' => 'deleted',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            \MEDIAESSENZ\Mail\Enumeration\MailType::INTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Enumeration\MailType::EXTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_INTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_EXTERNAL => 'mail-record',
        ],
        'useColumnsForDefaultValues' => 'from_email,from_name,reply_to_email,reply_to_name,organisation,priority,encoding,charset,send_options,type',
        'languageField' => 'sys_language_uid',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'subject' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.subject',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required',
            ],
        ],
        'page' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.page',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0,
            ],
        ],
        'from_email' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_email',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '80',
                'eval' => 'trim,required',
            ],
        ],
        'from_name' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'reply_to_email' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_email',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'reply_to_name' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'return_path' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.return_path',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'organisation' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.organisation',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'encoding' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.transfer_encoding',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['quoted-printable', 'quoted-printable'],
                    ['base64', 'base64'],
                    ['8bit', '8bit'],
                ],
                'default' => 'quoted-printable',
            ],
        ],
        'charset' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.charset',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '20',
                'eval' => 'trim',
                'default' => 'iso-8859-1',
            ],
        ],
        'priority' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.0', '5'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.1', '3'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.2', '1'],
                ],
                'default' => '3',
            ],
        ],
        'send_options' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.0', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.1', ''],
                ],
                'cols' => '2',
                'default' => '3',
            ],
        ],
        'include_media' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.includeMedia',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'flowed_format' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.flowedFormat',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'html_params' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'plain_params' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '&type=99',
            ],
        ],
        'sent' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.issent',
            'exclude' => '1',
            'config' => [
                'type' => 'none',
                'size' => 2,
            ],
        ],
        'scheduled' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled',
            'exclude' => '1',
            'config' => [
                'type' => 'none',
                'cols' => '30',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_begin' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_begin',
            'config' => [
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_end' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_end',
            'config' => [
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'redirect' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_rdct',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'redirect_url' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_rdct_url',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'redirect_all' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_mode',
            'config' => [
                'type' => 'check',
            ],
        ],
        'auth_code_fields' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.authcode_fieldList',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
                'default' => 'uid,name,email,password',
            ],
        ],
        'rendered_size' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.renderedsize',
            'exclude' => '1',
            'config' => [
                'type' => 'none',
            ],
        ],
        'attachment' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.attachment',
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                'attachment',
                [
                    'maxitems' => 5,
                    'appearance' => [
                        'createNewRelationLinkTitle' => 'LLL:EXT:frontend/locallang_ttc.xlf:images.addFileReference',
                    ],
                    // custom configuration for displaying fields in the overlay/reference table
                    // to use the image overlay palette instead of the basic overlay palette
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                    --palette--;;filePalette',
                            ],
                            \TYPO3\CMS\Core\Resource\File::FILETYPE_TEXT => [
                                'showitem' => '
                                    --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                    --palette--;;filePalette',
                            ],
                        ],
                    ],
                ],
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
            ),
        ],
        'recipient_groups' => [
            'label' => 'Recipient Groups',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'renderMode' => 'checkbox',
                'foreign_table' => 'tx_mail_domain_model_group',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 60,
            ]
        ],
        'mail_content' => [
            'label' => 'Mail content',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'query_info' => [
            'label' => 'Query info',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'recipients' => [
            'label' => 'Recipients',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'tstamp' => [
            'label' => 'Last modified',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.0', \MEDIAESSENZ\Mail\Enumeration\MailType::INTERNAL],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.1', \MEDIAESSENZ\Mail\Enumeration\MailType::EXTERNAL],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.2', \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_INTERNAL],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.3', \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_EXTERNAL],
                ],
                'default' => \MEDIAESSENZ\Mail\Enumeration\MailType::INTERNAL,
            ],
        ],
    ],
    'types' => [
        \MEDIAESSENZ\Mail\Enumeration\MailType::INTERNAL => ['showitem' => '
			--div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plain_params, html_params, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, send_options, include_media, flowed_format, redirect, redirect_all, auth_code_fields, scheduled, recipient_groups
		'],
        \MEDIAESSENZ\Mail\Enumeration\MailType::EXTERNAL => ['showitem' => '
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plain_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, html_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, send_options, include_media, flowed_format, redirect, redirect_all, auth_code_fields, scheduled, recipient_groups
		'],
        \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_INTERNAL => ['showitem' => '
			--div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plain_params, html_params, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, send_options, include_media, flowed_format, redirect, redirect_all, auth_code_fields, scheduled, recipient_groups
		'],
        \MEDIAESSENZ\Mail\Enumeration\MailType::DRAFT_EXTERNAL => ['showitem' => '
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plain_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, html_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, send_options, include_media, flowed_format, redirect, redirect_all, auth_code_fields, scheduled, recipient_groups
		'],
    ],
    'palettes' => [
        '1' => ['showitem' => 'scheduled_begin, scheduled_end, sent'],
        'from' => ['showitem' => 'from_email, from_name'],
        'reply' => ['showitem' => 'reply_to_email, reply_to_name'],
    ],
];
