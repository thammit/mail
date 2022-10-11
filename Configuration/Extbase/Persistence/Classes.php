<?php

declare(strict_types=1);

$useDirectMailTables = (bool)\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('mail', 'useDirectMailTables');
$mailPersistenceClasses = [];

if ($useDirectMailTables) {
    $mailPersistenceClasses = [
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
        \MEDIAESSENZ\Mail\Domain\Model\FrontendUser::class => [
            'tableName' => 'fe_users',
            'properties' => [
                'newsletter' => [
                    'fieldName' => 'module_sys_dmail_newsletter'
                ],
                'acceptsHtml' => [
                    'fieldName' => 'module_sys_dmail_html'
                ],
                'categories' => [
                    'fieldName' => 'module_sys_dmail_category'
                ],
            ]
        ],
        \MEDIAESSENZ\Mail\Domain\Model\Category::class => [
            'tableName' => 'sys_dmail_category',
//        'tableName' => 'sys_category',
            'properties' => [
                'title' => [
                    'fieldName' => 'category'
                ]
            ]
        ],
        \MEDIAESSENZ\Mail\Domain\Model\Log::class => [
            'tableName' => 'sys_dmail_maillog',
            'properties' => [
                'mail' => [
                    'fieldName' => 'mid'
                ],
                'recipientTable' => [
                    'fieldName' => 'rtbl'
                ],
                'recipientUid' => [
                    'fieldName' => 'rid'
                ],
                'formatSent' => [
                    'fieldName' => 'html_sent'
                ],
                'parseTime' => [
                    'fieldName' => 'parsetime'
                ],
                'lastChange' => [
                    'fieldName' => 'tstamp'
                ],
            ]
        ]
    ];
} else {
    $mailPersistenceClasses = [
        \MEDIAESSENZ\Mail\Domain\Model\Mail::class => [
            'tableName' => 'tx_mail_domain_model_mail',
            'properties' => [
                'lastModified' => [
                    'fieldName' => 'tstamp'
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
        ],
        \MEDIAESSENZ\Mail\Domain\Model\Address::class => [
            'tableName' => 'tt_address',
        ],
        \MEDIAESSENZ\Mail\Domain\Model\Category::class => [
            'tableName' => 'sys_category',
        ],
    ];
}

return $mailPersistenceClasses;
