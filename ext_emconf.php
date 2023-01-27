<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mail',
    'description' => 'Mail System for TYPO3',
    'category' => 'plugin',
    'version' => '1.2.0',
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
];
