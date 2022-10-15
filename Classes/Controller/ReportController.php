<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

class ReportController extends AbstractController
{
    /**
     * @throws Exception
     * @throws DBALException
     */
    public function indexAction(): ResponseInterface
    {
        $rows = $this->mailRepository->findSentByPid($this->id);
        $data = [];
        foreach ($rows as $row) {
            [$percentOfSent, $numberOfRecipients] = MailerUtility::calculatePercentOfSend((int)$row['count'], (int)$row['recipients']);
            $status = 'queuing';
            if (!empty($row['scheduled_begin'])) {
                if (!empty($row['scheduled_end'])) {
                    $status = 'sent';
                } else {
                    $status = 'sending';
                }
            }
            $data[] = [
                'uid' => $row['uid'],
                'subject' => $row['subject'],
                'scheduled' => $row['scheduled'],
                'scheduled_begin' => $row['scheduled_begin'],
                'scheduled_end' => $row['scheduled_end'],
                'sent' => $row['count'],
                'percentOfSent' => $percentOfSent,
                'numberOfRecipients' => $numberOfRecipients,
                'status' => $status,
            ];
        }
        $this->view->assignMultiple([
            'data' => $data,
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     */
    public function showAction(Mail $mail): ResponseInterface
    {
        $this->mailService->setMail($mail);
        $this->view->assignMultiple([
            'mailInfo' => $this->mailService->getMailInfo(),
            'generalInfo' => $this->mailService->getGeneralInfo(),
            'responsesInfo' => $this->mailService->getResponsesInfo(),
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

    /**
     * @throws StopActionException
     */
    public function recalculateCacheAction(Mail $mail): void
    {
        // todo add code to refresh cache
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }
}
