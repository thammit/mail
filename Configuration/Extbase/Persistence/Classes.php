<?php

declare(strict_types=1);

return [
    \MEDIAESSENZ\Mail\Domain\Model\Mail::class => [
        'tableName' => 'sys_dmail',
        'properties' => [
            'lastModified' => [
                'fieldName' => 'tstamp'
            ],
            'replyToEmail' => [
                'fieldName' => 'replyto_email'
            ],
            'replyToName' => [
                'fieldName' => 'replyto_name'
            ],
            'sendOptions' => [
                'fieldName' => 'sendOptions'
            ],
            'includeMedia' => [
                'fieldName' => 'includeMedia'
            ],
            'flowedFormat' => [
                'fieldName' => 'flowedFormat'
            ],
            'htmlParams' => [
                'fieldName' => 'HTMLParams'
            ],
            'plainParams' => [
                'fieldName' => 'plainParams'
            ],
            'sent' => [
                'fieldName' => 'issent'
            ],
            'renderedSize' => [
                'fieldName' => 'renderedsize'
            ],
            'mailContent' => [
                'fieldName' => 'mailContent'
            ],
            'redirect' => [
                'fieldName' => 'use_rdct'
            ],
            'redirectAll' => [
                'fieldName' => 'long_link_mode'
            ],
            'redirectUrl' => [
                'fieldName' => 'long_link_rdct_url'
            ],
            'authCodeFields' => [
                'fieldName' => 'authcode_fieldList'
            ],
            'recipientGroups' => [
                'fieldName' => 'recipientGroups'
            ],
        ],
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Group::class => [
        'tableName' => 'sys_dmail_group',
        'properties' => [
            'recordTypes' => [
                'fieldName' => 'whichtables'
            ],
            'categories' => [
                'fieldName' => 'select_categories'
            ],
            'children' => [
                'fieldName' => 'mail_groups'
            ],
        ]
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Category::class => [
        'tableName' => 'sys_dmail_category',
        'properties' => [
            'title' => [
                'fieldName' => 'category'
            ]
        ]
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Log::class => [
        'tableName' => 'sys_dmail_maillog',
        'properties' => [
        ]
    ],
];
