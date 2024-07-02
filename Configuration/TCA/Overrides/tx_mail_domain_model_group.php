<?php
defined('TYPO3') or die();

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_address')) {
    $allowed = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $GLOBALS['TCA']['tx_mail_domain_model_group']['columns']['static_list']['config']['allowed'], true);
    if (!in_array('tt_address', $allowed)) {
        $allowed[] = 'tt_address';
        $GLOBALS['TCA']['tx_mail_domain_model_group']['columns']['static_list']['config']['allowed'] = implode(',', $allowed);
    }
}
