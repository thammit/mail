<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Enumeration\ReturnCodes;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
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
        $this->view->assign('mails', $this->mailRepository->findSentByPid($this->id));

        $this->moduleTemplate->setContent($this->view->render());
        $this->addDocheaderButtons($this->request->getRequestTarget());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'general' => $this->reportService->getGeneralData(),
            'performance' => $this->reportService->getPerformanceData(),
            'returned' => $this->reportService->getReturnedData(),
            'responses' => $this->reportService->getResponsesData(),
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->addDocheaderButtons($this->request->getRequestTarget());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData(),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData());
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData(), 'total_returned');
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]), 'unknown');
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]), 'mailbox_full');
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]), 'bad_host');
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]), 'bad_header');
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
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'data' => $this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
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
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]), 'reason_unknown');
    }

    protected function addDocheaderButtons(string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($requestUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $shortCutButton = $buttonBar->makeShortcutButton()->setRouteIdentifier('MailMail_MailReport');
        $arguments = [
            'id' => $this->id,
        ];
        $potentialArguments = [
            'tx_mail_mailmail_mailreport' => ['mail', 'action', 'controller'],
        ];
        $displayName = 'Mail Reports [' . $this->id . ']';
        foreach ($potentialArguments as $argument => $subArguments) {
            if (!empty($this->request->getQueryParams()[$argument])) {
                foreach ($subArguments as $subArgument) {
                    $arguments[$argument][$subArgument] = $this->request->getQueryParams()[$argument][$subArgument];
                }
                $displayName = 'Mail Report [' . $arguments['tx_mail_mailmail_mailreport']['mail'] . ']';
            }
        }
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName($displayName);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

}
