<?php
defined('TYPO3') or die();

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_address')) {
    // tt_address modified
    $ttAddressCols = [
        'mail_active' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_active',
            'exclude' => true,
            'config' => [
                'type' => 'check'
            ]
        ],
        'mail_html' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_html',
            'exclude' => true,
            'config' => [
                'type' => 'check'
            ]
        ],
        'mail_salutation' => [
            'label' => 'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:mail_salutation',
            'exclude' => true,
            'config' => [
                'type' => 'input'
            ]
        ],
        'tstamp' => [
            'label' => 'Last modified',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
    ];

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_address', $ttAddressCols);
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('tt_address', '--div--;Mail,mail_active,mail_html,mail_salutation');
}
