<?php

use TYPO3\CMS\Core\Information\Typo3Version;

return [
    'ctrl' => [
        'hideTable' => true,
        'label' => 'subject',
        'default_sortby' => 'tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail',
        'delete' => 'deleted',
        'type' => 'type',
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            'default' => 'mail-record',
            \MEDIAESSENZ\Mail\Type\Enumeration\MailType::INTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Type\Enumeration\MailType::EXTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Type\Enumeration\MailType::DRAFT_INTERNAL => 'mail-record',
            \MEDIAESSENZ\Mail\Type\Enumeration\MailType::DRAFT_EXTERNAL => 'mail-record',
        ],
        'useColumnsForDefaultValues' => 'from_email,from_name,reply_to_email,reply_to_name,organisation,priority,encoding,charset,send_options,type',
        'languageField' => 'sys_language_uid',
    ],
    'columns' => [
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.type.I.0', \MEDIAESSENZ\Mail\Type\Enumeration\MailType::INTERNAL],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.type.I.1', \MEDIAESSENZ\Mail\Type\Enumeration\MailType::EXTERNAL],
                ],
                'default' => \MEDIAESSENZ\Mail\Type\Enumeration\MailType::INTERNAL,
            ],
        ],
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'status' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status.I.0', \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::DRAFT],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status.I.1', \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::SCHEDULED],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status.I.2', \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::SENDING],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status.I.3', \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::SENT],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.status.I.4', \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::ABORTED],
                ],
                'default' => \MEDIAESSENZ\Mail\Type\Enumeration\MailStatus::DRAFT,
            ],
        ],
        'subject' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.subject',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => '120',
                'eval' => 'trim,required',
            ],
        ],
        'page' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.page',
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
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.fromEmail',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 80,
                'eval' => 'trim,required',
            ],
        ],
        'from_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.fromName',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
            ],
        ],
        'reply_to_email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.replyToEmail',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
            ],
        ],
        'reply_to_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.replyToName',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
            ],
        ],
        'return_path' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.returnPath',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
            ],
        ],
        'organisation' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.organisation',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
            ],
        ],
        'encoding' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.transferEncoding',
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
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.charset',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => '20',
                'eval' => 'trim',
                'default' => 'iso-8859-1',
            ],
        ],
        'priority' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.priority',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.priority.I.0', '5'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.priority.I.1', '3'],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.priority.I.2', '1'],
                ],
                'default' => '3',
            ],
        ],
        'send_options' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.sendOptions',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.sendOptions.I.0', ''],
                    ['LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.sendOptions.I.1', ''],
                ],
                'cols' => '2',
                'default' => \MEDIAESSENZ\Mail\Type\Bitmask\SendFormat::BOTH,
            ],
        ],
        'include_media' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.includeMedia',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'html_params' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.htmlParams',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => 80,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'plain_params' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.plainParams',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => 80,
                'eval' => 'trim',
                'default' => '&plain=1',
            ],
        ],
        'sent' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.sent',
            'config' => [
                'type' => 'check',
                'readOnly' => true,
            ],
        ],
        'step' => [
            'label' => 'Step',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'scheduled' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.scheduled',
            'config' => [
                'type' => 'none',
                'cols' => 30,
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_begin' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.scheduledBegin',
            'config' => [
                'type' => 'none',
                'cols' => 15,
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_end' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.scheduledEnd',
            'config' => [
                'type' => 'none',
                'cols' => 15,
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'redirect' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.redirect',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'redirect_url' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.redirectUrl',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => 80,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'redirect_all' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.redirectAll',
            'config' => [
                'type' => 'check',
            ],
        ],
        'auth_code_fields' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.authCodeFields',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'max' => 80,
                'default' => 'uid,name,email,password',
            ],
        ],
        'rendered_size' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.renderedSize',
            'config' => [
                'type' => 'none',
            ],
        ],
        'attachment' => [
            'exclude' => true,
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.attachment',
            'config' => (new Typo3Version())->getMajorVersion() < 12 ? \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
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
            ) :
            [
                'type' => 'file',
                'maxitems' => 5,
                'allowed' => 'common-image-types, common-text-types, gz, zip'
            ],
        ],
        'recipient_groups' => [
            'exclude' => true,
            'label' => 'Recipient Groups',
            'config' => [
                'type' => 'select',
                'readOnly' => true,
                'renderType' => 'selectCheckBox',
                'renderMode' => 'checkbox',
                'foreign_table' => 'tx_mail_domain_model_group',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 60,
            ]
        ],
        'exclude_recipient_groups' => [
            'exclude' => true,
            'label' => 'Recipient Groups',
            'config' => [
                'type' => 'select',
                'readOnly' => true,
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
        'message_id' => [
            'label' => 'message id',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'html_content' => [
            'label' => 'html content',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'preview_image' => [
            'label' => 'preview image',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'plain_content' => [
            'label' => 'plain content',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'html_links' => [
            'label' => 'html links',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'plain_links' => [
            'label' => 'plain links',
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
        'number_of_recipients' => [
            'label' => 'Number of recipients',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ]
        ],
        'recipients_handled' => [
            'label' => 'Recipients handled',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'number_of_recipients_handled' => [
            'label' => 'Number of recipients handled',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ]
        ],
        'delivery_progress' => [
            'label' => 'Delivery progress in %',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ]
        ],
        'tstamp' => [
            'label' => 'Last modified',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
    ],
    'types' => [
        \MEDIAESSENZ\Mail\Type\Enumeration\MailType::INTERNAL => ['showitem' => '
			--div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.general, type,sys_language_uid, page, plain_params, html_params, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.headers, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.content, send_options, include_media, redirect, redirect_all, auth_code_fields,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.distribution, scheduled, --palette--;;progress
		'],
        \MEDIAESSENZ\Mail\Type\Enumeration\MailType::EXTERNAL => ['showitem' => '
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.general, type, page, plain_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.plainParams.ALT.1, html_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.htmlParams.ALT.1, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.headers, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.content, send_options, include_media, redirect, redirect_all, auth_code_fields,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.distribution, scheduled, --palette--;;progress
		'],
        \MEDIAESSENZ\Mail\Type\Enumeration\MailType::DRAFT_INTERNAL => ['showitem' => '
			--div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.general, type,sys_language_uid, page, plain_params, html_params, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.headers, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.content, send_options, include_media, redirect, redirect_all, auth_code_fields,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.distribution, scheduled, --palette--;;progress
		'],
        \MEDIAESSENZ\Mail\Type\Enumeration\MailType::DRAFT_EXTERNAL => ['showitem' => '
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.general, type, page, plain_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.plainParams.ALT.1, html_params;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.htmlParams.ALT.1, attachment,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.headers, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.content, send_options, include_media, redirect, redirect_all, auth_code_fields,
            --div--;LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:tx_mail_domain_model_mail.tab.distribution, scheduled, --palette--;;progress
		'],
    ],
    'palettes' => [
        '1' => ['showitem' => 'scheduled_begin, scheduled_end, sent'],
        'from' => ['showitem' => 'from_email, from_name'],
        'reply' => ['showitem' => 'reply_to_email, reply_to_name'],
        'progress' => ['showitem' => 'number_of_recipients, number_of_recipients_handled, delivery_progress, sent'],
    ],
];
