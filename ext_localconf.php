<?php
declare(strict_types=1);

defined('TYPO3') or die();

(function () {
    //\MEDIAESSENZ\Mail\Configuration::loadVendorLibraries();
    \MEDIAESSENZ\Mail\Configuration::addModuleTypoScript();
    \MEDIAESSENZ\Mail\Configuration::addPageTSConfig();
    \MEDIAESSENZ\Mail\Configuration::addUserTSConfig();
    \MEDIAESSENZ\Mail\Configuration::addTypoScriptContentObject();
    \MEDIAESSENZ\Mail\Configuration::registerFluidNameSpace();
    \MEDIAESSENZ\Mail\Configuration::registerTypeConverter();
    \MEDIAESSENZ\Mail\Configuration::directMailMigration();
})();;
