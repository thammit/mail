<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Hooks;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;

class PageTreeRefresh
{
    public function addJs(array $params, PageRenderer $pageRenderer): void
    {
        if ($pageRenderer->getApplicationType() === 'BE') {
            $backendUserAuthentication = $GLOBALS['BE_USER'];
            if ($backendUserAuthentication instanceof BackendUserAuthentication) {
                if ($backendUserAuthentication->getSessionData('updatePageTree')) {
                    $pageRenderer->addJsInlineCode('refreshPageTree', "top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'))");
                    $backendUserAuthentication->setAndSaveSessionData('updatePageTree', null);
                }
            }
        }
    }
}
