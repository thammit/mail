<?php
declare(strict_types=1);

defined('TYPO3') or die();

(static function () {
    if ((new TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
        \MEDIAESSENZ\Mail\Configuration::registerBackendModules();
    }
    \MEDIAESSENZ\Mail\Configuration::addMailStyleSheetDirectory();
    \MEDIAESSENZ\Mail\Configuration::addMailPageType();
})();
