<?php

defined('TYPO3') or die();

// add mail module entry
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5',
    \MEDIAESSENZ\Mail\Constants::MAIL_MODULE_NAME,
    'app-pagetree-folder-contains-mail',
];

if (!is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'] = [];
}

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-' . \MEDIAESSENZ\Mail\Constants::MAIL_MODULE_NAME] = 'app-pagetree-folder-contains-mail';

try {
    $mailPageType = (int)(\MEDIAESSENZ\Mail\Utility\ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') ?? \MEDIAESSENZ\Mail\Constants::DEFAULT_MAIL_PAGE_TYPE);
} catch (\Exception $e) {
    $mailPageType = \MEDIAESSENZ\Mail\Constants::DEFAULT_MAIL_PAGE_TYPE;
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
    'pages',
    'doktype',
    [
        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:pages.mail',
        $mailPageType,
        'app-pagetree-mail'
    ],
    '1',
    'after'
);

\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
    $GLOBALS['TCA']['pages'],
    [
        'ctrl' => [
            'typeicon_classes' => [
                $mailPageType => 'app-pagetree-mail',
            ],
        ],
        'types' => [
            $mailPageType => [
                'showitem' => '
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general, --palette--;;standard, --palette--;;title,
                        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.appearance, --palette--;;layout,
                        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.resources, --palette--;;config,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, --palette--;;language,
                        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access, --palette--;;visibility, --palette--;;access,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes, rowDescription,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended
                    '
            ],
        ],
    ]
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'mail',
    'Configuration/TsConfig/Page/ContentElement/AllowOnlySupportedContentElements.tsconfig',
    'MAIL: Allow only supported content elements'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'mail',
    'Configuration/TsConfig/Page/BackendLayouts/Mail.tsconfig',
    'MAIL: Add simple mail backend layout'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'mail',
    'Configuration/TsConfig/Page/TCADefaults.tsconfig',
    'MAIL: Default settings for mail pages'
);
