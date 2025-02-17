<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mail',
    'description' => 'Powerful newsletter system for TYPO3',
    'category' => 'module',
    'author' => 'Alexander Grein',
    'author_email' => 'alexander.grein@gmail.com',
    'author_company' => 'MEDIA::ESSENZ',
    'version' => '3.2.8',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.4.99',
            'typo3' => '11.5.0-13.4.99',
            'redirects' => '11.5.0-13.4.99',
            'scheduler' => '11.5.0-13.4.99',
            'fluid_styled_content' => '11.5.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
            'jumpurl' => '8.0.0-8.99.99',
            'tt_address' => '7.0.0-9.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'MEDIAESSENZ\\Mail\\' => 'Classes'
        ]
    ],
];
