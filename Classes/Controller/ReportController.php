<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

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
            'mail' => $mail,
            'general' => $this->mailService->getGeneralData(),
            'performance' => $this->mailService->getPerformanceData(),
            'returned' => $this->mailService->getReturnedData(),
            'responses' => $this->mailService->getResponsesData(),
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
            'data' => $this->mailService->getReturnedDetailsData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function disableTotalReturnedAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportTotalReturnedAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData());
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
            'data' => $this->mailService->getUnknownData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function disableUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getUnknownData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getUnknownData());
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
            'data' => $this->mailService->getMailboxFullData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function disableFullAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getMailboxFullData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportFullAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getMailboxFullData());
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
            'data' => $this->mailService->getBadHostData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function disableBadHostAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getBadHostData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportBadHostAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getBadHostData());
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
            'data' => $this->mailService->getBadHeaderData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function disableBadHeaderAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getBadHeaderData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportBadHeaderAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getBadHeaderData());
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
            'data' => $this->mailService->getReasonUnknownData(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function disableReasonUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReasonUnknownData());
        ViewUtility::addOkToFlashMessageQueue($affectedRecipients . ' recipients successfully disabled.', '', true);
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function csvExportReasonUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReasonUnknownData());
    }
}
