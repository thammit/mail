<?php
declare(strict_types=1);

defined('TYPO3') or die();

(function () {
    \MEDIAESSENZ\Mail\Configuration::registerBackendModules();
    \MEDIAESSENZ\Mail\Configuration::addMailPageType();
})();
