<?php
declare(strict_types=1);

defined('TYPO3') or die();

(function () {
    \MEDIAESSENZ\Mail\Configuration::addPageTSConfig();
    \MEDIAESSENZ\Mail\Configuration::registerHooks();
    \MEDIAESSENZ\Mail\Configuration::registerFluidNameSpace();
})();;
