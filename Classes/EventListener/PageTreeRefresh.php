<?php

namespace MEDIAESSENZ\Mail\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;

class PageTreeRefresh
{
    public function __construct(readonly PageRenderer $pageRenderer) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $id = (int)($event->getRequest()->getQueryParams()['id'] ?? 0);
        $updatePageTree = $this->getBackendUser()->getSessionData('updatePageTree');
        if ($updatePageTree || $id !== $this->getBackendUser()->getSessionData('lastSelectedPage')) {
            $this->pageRenderer->addJsInlineCode('refreshPageTree', "if (top) { top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'));}", false, true);
            if ($updatePageTree) {
                $this->getBackendUser()->setAndSaveSessionData('updatePageTree', null);
            }
        }
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
