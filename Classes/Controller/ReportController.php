<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
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
     * @throws SiteNotFoundException
     */
    public function showAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mailUid' => $mail->getUid(),
            'mailInfo' => $this->mailService->getMailInfo(),
            'generalInfo' => $this->mailService->getGeneralInfo(),
            'responsesInfo' => $this->mailService->getResponsesInfo(),
            'returnedMails' => $this->mailService->getReturnedMails(),
            'linkResponses' => $this->mailService->getLinkResponses(),
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

    public function showTotalReturnedAction(): void
    {
        // returnList
        $this->redirect('show');
    }

    public function disableTotalReturnedAction(): void
    {
        // returnDisable
        $this->redirect('show');
    }

    public function csvExportTotalReturnedAction(): void
    {
        // returnCSV
        $this->redirect('show');
    }

    public function showUnknownAction(): void
    {
        // unknownList
        $this->redirect('show');
    }

    public function disableUnknownAction(): void
    {
        // unknownDisable
        $this->redirect('show');
    }

    public function csvExportUnknownAction(): void
    {
        // unknownCSV
        $this->redirect('show');
    }

    public function showFullAction(): void
    {
        // fullList
        $this->redirect('show');
    }

    public function disableFullAction(): void
    {
        // fullDisable
        $this->redirect('show');
    }

    public function csvExportFullAction(): void
    {
        // fullCSV
        $this->redirect('show');
    }

    public function showBadHostAction(): void
    {
        // badHostList
        $this->redirect('show');
    }

    public function disableBadHostAction(): void
    {
        // badHostDisable
        $this->redirect('show');
    }

    public function csvExportBadHostAction(): void
    {
        // badHostCSV
        $this->redirect('show');
    }

    public function showBadHeaderAction(): void
    {
        // badHeaderList
        $this->redirect('show');
    }

    public function disableBadHeaderAction(): void
    {
        // badHeaderDisable
        $this->redirect('show');
    }

    public function csvExportBadHeaderAction(): void
    {
        // badHeaderCSV
        $this->redirect('show');
    }

    public function showReasonUnknownAction(): void
    {
        // reasonUnknownList
        $this->redirect('show');
    }

    public function disableReasonUnknownAction(): void
    {
        // reasonUnknownDisable
        $this->redirect('show');
    }

    public function csvExportReasonUnknownAction(): void
    {
        // reasonUnknownCSV
        $this->redirect('show');
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
