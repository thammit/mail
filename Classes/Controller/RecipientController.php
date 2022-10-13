<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

class RecipientController  extends AbstractController
{
    /**
     * @return ResponseInterface
     * @throws RouteNotFoundException
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
        $data = [
            'rows' => [],
        ];

        $recipientGroups = $this->groupRepository->findByPid($this->id);

        /** @var Group $recipientGroup */
        foreach ($recipientGroups as $recipientGroup) {
            $result = $this->recipientService->compileMailGroup($recipientGroup->getUid());
            $totalRecipients = 0;
            $idLists = $result['queryInfo']['id_lists'];

            if (is_array($idLists['tt_address'] ?? false)) {
                $totalRecipients += count($idLists['tt_address']);
            }
            if (is_array($idLists['fe_users'] ?? false)) {
                $totalRecipients += count($idLists['fe_users']);
            }
            if (is_array($idLists['PLAINLIST'] ?? false)) {
                $totalRecipients += count($idLists['PLAINLIST']);
            }
            if (is_array($idLists[$this->userTable] ?? false)) {
                $totalRecipients += count($idLists[$this->userTable]);
            }

            $data['rows'][] = [
                'uid' => $recipientGroup->getUid(),
                'title' => $recipientGroup->getTitle(),
                'type' => $recipientGroup->getType(),
                'typeProcessed' => htmlspecialchars(BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'type', $recipientGroup->getType())),
                'description' => BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'description', htmlspecialchars($recipientGroup->getDescription())),
                'count' => $totalRecipients,
            ];
        }

        $this->view->assignMultiple([
            'type' => 4,
            'data' => $data,
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @return void
     * @throws StopActionException
     */
    public function showAction(Group $group): void
    {
        $this->redirect('index');
    }

    public function csvImportWizardAction(): void
    {
        $this->redirect('index');
    }
}
