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
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

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

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function showTotalReturnedAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getReturnedList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableTotalReturnedAction(Mail $mail): void
    {
        // todo returnDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    public function csvExportTotalReturnedAction(): void
    {
        // todo returnCSV
        $this->redirect('show');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function showUnknownAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getUnknownList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableUnknownAction(Mail $mail): void
    {
        // todo unknownDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    public function csvExportUnknownAction(Mail $mail): void
    {
        // todo unknownCSV
        $this->redirect('show');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function showFullAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getFullList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableFullAction(Mail $mail): void
    {
        // todo fullDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function csvExportFullAction(Mail $mail): void
    {
        // todo fullCSV
        $this->redirect('show');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function showBadHostAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getBadHostList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableBadHostAction(Mail $mail): void
    {
        // todo badHostDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function csvExportBadHostAction(Mail $mail): void
    {
        // todo badHostCSV
        $this->redirect('show');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function showBadHeaderAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getBadHeaderList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableBadHeaderAction(Mail $mail): void
    {
        // todo badHeaderDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function csvExportBadHeaderAction(Mail $mail): void
    {
        // todo badHeaderCSV
        $this->redirect('show');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException+
     */
    public function showReasonUnknownAction(Mail $mail): ResponseInterface
    {
        $this->mailService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->mailService->getReasonUnknownList(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function disableReasonUnknownAction(Mail $mail): void
    {
        // todo reasonUnknownDisable
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     */
    public function csvExportReasonUnknownAction(Mail $mail): void
    {
        // todo reasonUnknownCSV
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
