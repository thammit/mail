<?php

return [
    'dependencies' => [
        'core',
    ],
    'imports' => [
        '@mediaessenz/mail/' => [
            'path' => 'EXT:mail/Resources/Public/JavaScript/Modules/',
            'exclude' => [
                'EXT:mail/Resources/Public/JavaScript/Contrib/',
            ],
        ],
        '@mediaessenz/mail/html2canvas.js' => 'EXT:mail/Resources/Public/JavaScript/Contrib/html2canvas.esm.js',
    ],
];
