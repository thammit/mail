<?php

return [
    'frontend' => [
        'mail/simulate-frontend-user-group' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\SimulateFrontendUserGroupMiddleware::class,
            'before' => [
                'typo3/cms-redirects/redirecthandler',
            ],
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
        'mail/jumpurl' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\JumpurlMiddleware::class,
            'before' => [
                'friends-of-typo3/rdct/send-redirect',
            ],
        ],
        'mail/plain' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\MarkdownMiddleware::class,
            'before' => [
                'typo3/cms-frontend/output-compression',
            ],
        ],
    ],
];
