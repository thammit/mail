<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Exception;
use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Type\Enumeration\ReturnCodes;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ScssParserUtility;
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
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;

class ReportController extends AbstractController
{
    /**
     * @throws Exception
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            // current selected page has no mail module configuration -> redirect to closest mail module page
            $mailModulePageId = BackendDataUtility::getClosestMailModulePageId($this->id);
            if ($mailModulePageId) {
                if ($this->typo3MajorVersion < 12) {
                    // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                    $_GET['id'] = $mailModulePageId;
                }
                return $this->redirect('index', null, null, ['id' => $mailModulePageId]);
            }
        }

        $this->view->assignMultiple([
            'mails' => $this->mailRepository->findSentByPid($this->id),
            'hideDeleteReportButton' => $this->userTSConfiguration['hideDeleteReportButton'] ?? false,
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->addDocheaderButtons($this->request->getRequestTarget());

        $refreshRate = (int)($this->pageTSConfiguration['refreshRate'] ?? 5);
        $this->pageRenderer->addInlineSetting('Mail', 'refreshRate', $refreshRate);
        if ($refreshRate) {
            $this->pageRenderer->addInlineSetting('Mail', 'refreshRate', $refreshRate);
            if ($this->typo3MajorVersion < 12) {
                $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/QueueRefresher');
            } else {
                $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/queue-refresher.js');
            }
        }

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws SiteNotFoundException
     * @throws NoSuchPropertyException
     * @throws UnknownClassException
     */
    public function showAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $this->assignFieldGroups($mail, true);
        $performance = $this->reportService->getPerformanceData();
        if ($performance['failedResponses']) {
            $this->view->assign('returned', $this->reportService->getReturnedData());
        }
        $this->view->assignMultiple([
            'mail' => $mail,
            'performance' => $performance,
            'responses' => $this->reportService->getResponsesData(),
            'maxLabelLength' => (int)($this->pageTSConfiguration['maxLabelLength'] ?? 0),
        ]);
        $this->moduleTemplate->setContent($this->view->render());
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
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableTotalReturnedAction(Mail $mail): void
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData());
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportTotalReturnedAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData()[$recipientSource], 'total_returned');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportUnknownAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_UNKNOWN, ReturnCodes::MAILBOX_INVALID])[$recipientSource], 'unknown');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableFullAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportFullAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData([ReturnCodes::MAILBOX_FULL])[$recipientSource], 'mailbox_full');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableBadHostAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportBadHostAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData([ReturnCodes::RECIPIENT_NOT_LOCAL])[$recipientSource], 'bad_host');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableBadHeaderAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportBadHeaderAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData([ReturnCodes::TRANSACTION_FAILED])[$recipientSource], 'bad_header');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function disableReasonUnknownAction(Mail $mail): ResponseInterface
    {
        $this->reportService->init($mail);
        $affectedRecipients = $this->recipientService->disableRecipients($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON]));
        ViewUtility::addNotificationSuccess(sprintf(LanguageUtility::getLL('report.notification.recipientsDisabled.message'), $affectedRecipients));
        return $this->redirect('show', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(Mail $mail):ResponseInterface
    {
        $this->logRepository->deleteByMailUid($mail->getUid());
        $this->mailRepository->remove($mail);
        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.deleted.message'), $mail->getSubject()),
            LanguageUtility::getLL('general.notification.severity.success.title')
        );
        ScssParserUtility::deleteCacheFiles();
        return $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @param string $recipientSource
     * @return ResponseInterface
     * @throws Exception
     * @throws InvalidQueryException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function csvExportReasonUnknownAction(Mail $mail, string $recipientSource): ResponseInterface
    {
        $this->reportService->init($mail);
        return CsvUtility::csvDownloadRecipientsCSV($this->reportService->getReturnedDetailsData([ReturnCodes::UNKNOWN_REASON])[$recipientSource], 'reason_unknown');
    }

    protected function addDocheaderButtons(string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($requestUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);

        $routeIdentifier = $this->typo3MajorVersion < 12 ? 'MailMail_MailReport' : 'mail_report';
        $shortCutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier($routeIdentifier);
        $arguments = [
            'id' => $this->id,
        ];
        $potentialArguments = [
            'tx_mail_mailmail_mailreport' => ['mail', 'action', 'controller'],
        ];
        $displayName = LanguageUtility::getLL('shortcut.reports') . ' [' . $this->id . ']';
        foreach ($potentialArguments as $argument => $subArguments) {
            if (!empty($this->request->getQueryParams()[$argument])) {
                foreach ($subArguments as $subArgument) {
                    if ($this->request->getQueryParams()[$argument][$subArgument] ?? false) {
                        $arguments[$argument][$subArgument] = $this->request->getQueryParams()[$argument][$subArgument];
                    }
                }
                if ($arguments['tx_mail_mailmail_mailreport']['mail'] ?? false) {
                    $displayName = LanguageUtility::getLL('shortcut.report') . ' [' . $arguments['tx_mail_mailmail_mailreport']['mail'] . ']';
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
