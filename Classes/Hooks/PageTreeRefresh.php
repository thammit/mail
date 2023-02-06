<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Hooks;

use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;

class PageTreeRefresh
{
    public function __construct(private PageRenderer $pageRenderer)
    {}

    public function addJs(): void
    {
        if ($this->pageRenderer->getApplicationType() === 'BE') {
            $backendUserAuthentication = $GLOBALS['BE_USER'];
            if ($backendUserAuthentication instanceof BackendUserAuthentication) {
                if ($backendUserAuthentication->getSessionData('updatePageTree')) {
                    $this->pageRenderer->addJsInlineCode('refreshPageTree', "top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'))", false, true);
                    $backendUserAuthentication->setAndSaveSessionData('updatePageTree', null);
                }
            }
        }
    }

    public function addHeaderJs(array $params, PageLayoutController $pageLayoutController): void
    {
        if ($pageLayoutController->id !== $GLOBALS['BE_USER']->getSessionData('lastSelectedPage')) {
            $this->pageRenderer->addJsInlineCode('refreshPageTree', "top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'))", false, true);
        }
    }
}
