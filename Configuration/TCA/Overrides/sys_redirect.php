<?php

defined('TYPO3') or die;

if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() >= 12) {
    $GLOBALS['TCA']['sys_redirect']['columns']['creation_type']['config']['items'][] = [
        'LLL:EXT:mail/Resources/Private/Language/locallang_tca.xlf:sys_redirect.creation_type',
        (int)(\MEDIAESSENZ\Mail\Utility\ConfigurationUtility::getExtensionConfiguration('mailRedirectCreationTypeNumber') ?? \MEDIAESSENZ\Mail\Constants::DEFAULT_MAIL_REDIRECT_CREATION_TYPE)
    ];
}
