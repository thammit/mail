<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Hooks;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;

class PageTreeRefresh
{
    public function __construct(private PageRenderer $pageRenderer)
    {
    }

    public function addJs(): void
    {
        if ($this->getApplicationType() === 'BE') {
            if ($this->getBackendUser() instanceof BackendUserAuthentication) {
                if ($this->getBackendUser()->getSessionData('updatePageTree')) {
                    $this->addRefreshInlineCode();
                    $this->getBackendUser()->setAndSaveSessionData('updatePageTree', null);
                }
            }
        }
    }

    public function addHeaderJs(): void
    {
        if ($this->getId() !== $this->getBackendUser()->getSessionData('lastSelectedPage')) {
            $this->addRefreshInlineCode();
        }
    }

    protected function addRefreshInlineCode(): void
    {
        $this->pageRenderer->addJsInlineCode('refreshPageTree', "if (top) { top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh')); }", false,
            true);
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getApplicationType(): string
    {
        if (
            ($this->getRequest() ?? null) instanceof ServerRequestInterface &&
            ApplicationType::fromRequest($this->getRequest())->isFrontend()
        ) {
            return 'FE';
        }

        return 'BE';
    }

    protected function getId(): int
    {
        return (int)($this->getRequest()->getParsedBody()['id'] ?? $this->getRequest()->getQueryParams()['id'] ?? 0);
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
