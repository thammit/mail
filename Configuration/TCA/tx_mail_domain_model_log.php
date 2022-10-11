<?php
return [
    'ctrl' => [
        'hideTable' => true,
        'label' => 'mail',
        'label_alt' => 'recipient_table,recipient_uid',
        'label_alt_force' => true,
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'title' => 'Mail Log',
        'delete' => '',
        'typeicon_column' => 'recipient_table',
        'typeicon_classes' => [
            'f' => 'mail-log',
            't' => 'mail-log',
            'P' => 'mail-log',
        ],
    ],
    'columns' => [
        'tstamp' => [
            'label' => 'Last modified',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'mail' => [
            'label' => 'Mail',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'recipient' => [
            'label' => 'Recipient',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users,tt_address,tx_mail_domain_model_recipient',
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => false,
                    ],
                    'addRecord' => [
                        'disabled' => false,
                    ],
                    'listModule' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
        'recipient_uid' => [
            'label' => 'Recipient Uid',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'recipient_table' => [
            'label' => 'Recipient Table',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'url' => [
            'label' => 'Url',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'size' => [
            'label' => 'Size',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'parse_time' => [
            'label' => 'Parse time',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'format_sent' => [
            'label' => 'Parse time',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'url_id' => [
            'label' => 'Url id',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'return_content' => [
            'label' => 'Return content',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'return_code' => [
            'label' => 'Return code',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
    ],
];
