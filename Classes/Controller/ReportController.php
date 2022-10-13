<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use Psr\Http\Message\ResponseInterface;

class ReportController  extends AbstractController
{
    public function indexAction(): ResponseInterface
    {
        $this->view->assignMultiple([
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }
}
