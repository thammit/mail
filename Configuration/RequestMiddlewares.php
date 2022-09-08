<?php

return [
    'frontend' => [
        'mail/jumpurl-controller' => [
            'target' => \MEDIAESSENZ\Mail\Middleware\JumpurlController::class,
            'before' => [
                'friends-of-typo3/jumpurl',
            ],
        ],
    ],
];
