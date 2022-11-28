<?php

declare(strict_types=1);

return [
    \MEDIAESSENZ\Mail\Domain\Model\Mail::class => [
        'tableName' => 'tx_mail_domain_model_mail',
        'properties' => [
            'lastModified' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Group::class => [
        'tableName' => 'tx_mail_domain_model_group',
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Log::class => [
        'tableName' => 'tx_mail_domain_model_log',
    ],
    \MEDIAESSENZ\Mail\Domain\Model\FrontendUser::class => [
        'tableName' => 'fe_users',
        'properties' => [
            'active' => [
                'fieldName' => 'mail_active',
            ],
        ],
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Address::class => [
        'tableName' => 'tt_address',
        'properties' => [
            'disable' => [
                'fieldName' => 'hidden',
            ],
            'active' => [
                'fieldName' => 'mail_active',
            ],
        ],
    ],
    \MEDIAESSENZ\Mail\Domain\Model\Category::class => [
        'tableName' => 'sys_category',
    ],
];
