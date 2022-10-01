<?php

declare(strict_types=1);

return [
    \MEDIAESSENZ\Mail\Domain\Model\Mail::class => [
        'tableName' => 'sys_dmail',
        'properties' => [
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
            'size' => [
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
];
