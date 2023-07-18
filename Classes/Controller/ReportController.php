<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Driver\Exception;
use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Enumeration\ReturnCodes;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class ReportController extends AbstractController
{
    /**
     * @throws Exception
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
        $this->addLeftDocheaderBackButtons();
        $this->addDocheaderButtons($this->request->getRequestTarget());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showTotalReturnedAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData(),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
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
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportTotalReturnedAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData()[$recipientSource], 'total_returned');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws UnknownObjectException
     */
    public function disableUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportUnknownAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID])[$recipientSource], 'unknown');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showFullAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws UnknownObjectException
     */
    public function disableFullAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportFullAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL])[$recipientSource], 'mailbox_full');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showBadHostAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws UnknownObjectException
     */
    public function disableBadHostAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportBadHostAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL])[$recipientSource], 'bad_host');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showBadHeaderAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws UnknownObjectException
     */
    public function disableBadHeaderAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportBadHeaderAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED])[$recipientSource], 'bad_header');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    public function showReasonUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->view->assignMultiple([
            'mail' => $mail,
            'recipientSources' => $this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]),
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws UnknownObjectException
     */
    public function disableReasonUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->reportService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return void
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     */
    #[NoReturn] public function csvExportReasonUnknownAction(Mail $mail, string $recipientSource): void
    {
        $this->reportService->init($mail);
        $this->reportService->csvDownloadRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON])[$recipientSource], 'reason_unknown');
    }

    protected function addDocheaderButtons(string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($requestUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $shortCutButton = $buttonBar->makeShortcutButton()->setRouteIdentifier('mail_report');
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
                    if ($this->request->getQueryParams()[$argument][$subArgument] ?? false) {
                        $arguments[$argument][$subArgument] = $this->request->getQueryParams()[$argument][$subArgument];
                    }
                }
                if ($arguments['tx_mail_mailmail_mailreport']['mail'] ?? false) {
                    $displayName = 'Mail Report [' . $arguments['tx_mail_mailmail_mailreport']['mail'] . ']';
                }
            }
        }
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName($displayName);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    /**
     * Create document header back buttons of "show" action
     */
    protected function addLeftDocheaderBackButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $addBackButton = $buttonBar
            ->makeLinkButton()
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', Icon::SIZE_SMALL))
            ->setClasses('btn btn-default text-uppercase')
            ->setTitle(LanguageUtility::getLL('general.button.back'))
            ->setHref($this->uriBuilder->uriFor('index'));
        $buttonBar->addButton($addBackButton);
    }

}
