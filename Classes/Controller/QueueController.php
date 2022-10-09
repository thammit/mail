<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Psr\Http\Message\ResponseInterface;

class QueueController extends AbstractController
{
    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }
}
