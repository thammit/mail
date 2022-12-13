<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mail',
    'description' => 'Newsletter System for TYPO3',
    'category' => 'plugin',
    'version' => '0.1.0',
    'state' => 'beta',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            'jumpurl' => '8.0.0-8.99.99',
            'tt_address' => '6.0.0-6.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
