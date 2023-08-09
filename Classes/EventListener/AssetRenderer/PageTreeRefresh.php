<?php

namespace MEDIAESSENZ\Mail\EventListener\AssetRenderer;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\Event\BeforeJavaScriptsRenderingEvent;

class PageTreeRefresh
{
    public function __invoke(BeforeJavaScriptsRenderingEvent $event): void
    {
        if ($this->getApplicationType() === 'BE' &&
            $this->getBackendUser() instanceof BackendUserAuthentication &&
            $this->getBackendUser()->getSessionData('updatePageTree')
        ) {
            $event->getAssetCollector()->addInlineJavaScript('refreshPageTree',
                "if (top) { top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh')); }");
            $this->getBackendUser()->setAndSaveSessionData('updatePageTree', null);
        }
    }

    public function getApplicationType(): string
    {
        if (
            ($this->getRequest() ?? null) instanceof ServerRequestInterface &&
            ApplicationType::fromRequest($this->getRequest())->isFrontend()
        ) {
            return 'FE';
        }

        return 'BE';
    }

    protected function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
