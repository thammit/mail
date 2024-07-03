<?php
declare(strict_types=1);

defined('TYPO3') or die();

(static function () {
    \MEDIAESSENZ\Mail\Configuration::addModuleTypoScript();
    \MEDIAESSENZ\Mail\Configuration::addPageTSConfig();
    \MEDIAESSENZ\Mail\Configuration::addUserTSConfig();
    \MEDIAESSENZ\Mail\Configuration::registerFluidNameSpace();
    \MEDIAESSENZ\Mail\Configuration::registerMigrations();
    \MEDIAESSENZ\Mail\Configuration::excludeMailParamsFromCHashCalculation();
    \MEDIAESSENZ\Mail\Configuration::includeRequiredLibrariesForNoneComposerMode();

    if ((new TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
        \MEDIAESSENZ\Mail\Configuration::registerHooks();
        \MEDIAESSENZ\Mail\Configuration::addTypoScriptContentObject();
        \MEDIAESSENZ\Mail\Configuration::registerTypeConverter();
    }

})();
