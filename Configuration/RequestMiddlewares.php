<?php

return [
    'frontend' => [
        'mail/jumpurl' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\JumpurlMiddleware::class,
            'before' => [
                'friends-of-typo3/jumpurl',
            ],
        ],
        'mail/plain' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\MarkdownMiddleware::class,
            'before' => [
                'typo3/cms-frontend/output-compression',
            ],
        ],
    ],
    'backend' => [
        'mail/filter-page-tree' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\FilterPageTreeMiddleware::class,
            'after' => [
                'typo3/cms-backend/authentication'
            ],
        ],
    ]
];
