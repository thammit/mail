<?php
defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('mail', 'Configuration/TypoScript/boundaries/', 'Mail Content Boundaries');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('mail', 'Configuration/TypoScript/plaintext/', 'Mail Plain text');
