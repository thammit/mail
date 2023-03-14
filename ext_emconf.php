<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mail',
    'description' => 'Powerful newsletter system for TYPO3',
    'category' => 'module',
    'author' => 'Alexander Grein',
    'author_email' => 'alexander.grein@gmail.com',
    'author_company' => 'MEDIA::ESSENZ',
    'version' => '1.7.9',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.2.99',
            'typo3' => '11.5.0-11.5.99',
            'redirects' => '11.5.0-11.5.99',
            'scheduler' => '11.5.0-11.5.99',
            'fluid_styled_content' => '11.5.0-11.5.99',
            'jumpurl' => '8.0.0-8.99.99',
            'tt_address' => '6.0.0-7.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'MEDIAESSENZ\\Mail\\' => 'Classes'
        ]
    ],
];
