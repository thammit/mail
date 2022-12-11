<?php
return [
    'ctrl' => [
        'hideTable' => true,
        'label' => 'mail',
        'label_alt' => 'recipient_source,recipient_uid',
        'label_alt_force' => true,
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'title' => 'Mail Log',
        'delete' => '',
        'typeicon_column' => 'recipient_source',
        'typeicon_classes' => [
            'fe_users' => 'mail-log',
            'tt_address' => 'mail-log',
            'tx_mail_domain_model_group' => 'mail-log',
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
        'recipient_uid' => [
            'label' => 'Recipient Uid',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'recipient_source' => [
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
