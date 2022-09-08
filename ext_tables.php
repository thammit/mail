<?php
declare(strict_types=1);

defined('TYPO3') || die();

(function () {
    \MEDIAESSENZ\Mail\Configuration::addPageTSConfig();
    \MEDIAESSENZ\Mail\Configuration::registerTranslations();
    \MEDIAESSENZ\Mail\Configuration::registerBackendModules();
})();
