<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Enumeration\ReturnCodes;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
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
            $numberOfRecipients = array_sum(array_map('count', json_decode($row['recipients'], true, 512,  JSON_OBJECT_AS_ARRAY)));
            [$percentOfSent, $numberOfRecipients] = MailerUtility::calculatePercentOfSend((int)$row['count'], (int)$numberOfRecipients);
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
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
    #[NoReturn] public function csvExportTotalReturnedAction(Mail $mail): void
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
            'data' => $this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]),
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
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]));
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
    #[NoReturn] public function csvExportUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]));
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
            'data' => $this->mailService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]),
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
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]));
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
    #[NoReturn] public function csvExportFullAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]));
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
            'data' => $this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]),
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
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]));
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
    #[NoReturn] public function csvExportBadHostAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]));
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
            'data' => $this->mailService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]),
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
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]));
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
    #[NoReturn] public function csvExportBadHeaderAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]));
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
            'data' => $this->mailService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]),
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
        $affectedRecipients = $this->mailService->disableRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]));
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
    #[NoReturn] public function csvExportReasonUnknownAction(Mail $mail): void
    {
        $this->mailService->init($mail);
        $this->mailService->csvDownloadRecipients($this->mailService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]));
    }
}
