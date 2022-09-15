<?php

return [
    'frontend' => [
        'mail/simulate-frontend-user-group' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\SimulateFrontendUserGroupMiddleware::class,
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
        'mail/jumpurl-controller' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\JumpurlMiddleware::class,
            'before' => [
                'friends-of-typo3/jumpurl',
            ],
        ],
    ],
];
