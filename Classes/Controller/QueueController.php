<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class QueueController extends AbstractController
{
    /**
     * @return ResponseInterface
     * @throws InvalidQueryException
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->id === 0 || $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            $mailModulePageId = BackendDataUtility::getClosestMailModulePageId($this->id);
            if ($mailModulePageId) {
                if ($this->typo3MajorVersion < 12) {
                    // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                    $_GET['id'] = $mailModulePageId;
                }
                return $this->redirect('index', null, null, ['id' => $mailModulePageId]);
            }
            return $this->redirect('noPageSelected');
        }

        $refreshRate = (int)($this->pageTSConfiguration['refreshRate'] ?? 5);

        $assignments = [
            'id' => $this->id,
            'mails' => $this->mailRepository->findScheduledByPid($this->id, (int)($this->pageTSConfiguration['queueLimit'] ?? 10)),
            'sendPerCycle' => (int)($this->pageTSConfiguration['sendPerCycle'] ?? 50),
            'queueLimit' => (int)($this->pageTSConfiguration['queueLimit'] ?? 10),
            'refreshRate' => $refreshRate,
            'hideManualSendingButton' => $this->userTSConfiguration['hideManualSendingButton'] ?? false,
            'hideDeleteRunningSendingButton' => $this->userTSConfiguration['hideDeleteRunningSendingButton'] ?? false,
        ];

        $this->configureOverViewDocHeader($this->request->getRequestTarget(), !($this->userTSConfiguration['hideManualSending'] ?? false) && !($this->userTSConfiguration['hideConfiguration'] ?? false));

        if ($refreshRate) {
            $this->pageRenderer->addInlineSetting('Mail', 'refreshRate', $refreshRate);
            if ($this->typo3MajorVersion < 12) {
                $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/QueueRefresher');
            } else {
                $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/queue-refresher.js');
            }
        }

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Queue/Index');
    }

    public function saveConfigurationAction(int $sendPerCycle, int $queueLimit, int $refreshRate): ResponseInterface
    {
        $pageTS['sendPerCycle'] = (string)$sendPerCycle;
        $pageTS['queueLimit'] = (string)$queueLimit;
        $pageTS['refreshRate'] = (string)$refreshRate;
        $success = TypoScriptUtility::updatePagesTSConfig($this->id, $pageTS, 'mod.web_modules.mail.');
        if ($success) {
            ViewUtility::addNotificationSuccess(
                sprintf(LanguageUtility::getLL('configuration.notification.savedOnPage.message'), $this->id),
                LanguageUtility::getLL('general.notification.severity.success.title')
            );

            return $this->redirect('index');
        }
        return $this->redirect('index');
    }

    /**
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function triggerAction(): ResponseInterface
    {
        if (!$this->mailRepository->findMailToSend()) {
            ViewUtility::addNotificationInfo(
                LanguageUtility::getLL('queue.notification.nothingToDo.message'),
                LanguageUtility::getLL('queue.notification.nothingToDo.title')
            );
            return $this->redirect('index');
        }
        $this->mailerService->start((int)($this->pageTSConfiguration['sendPerCycle'] ?? 50));
        $this->mailerService->handleQueue();
        ViewUtility::addNotificationSuccess(
            LanguageUtility::getLL('queue.notification.mailSendTriggered.message'),
            LanguageUtility::getLL('general.notification.severity.success.title')
        );
        return $this->redirect('index');
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
        return $this->redirect('index');
    }

    public function state(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Mail $mail */
        $mail = $this->mailRepository->findByUid((int)($request->getQueryParams()['mail']));
        return $this->jsonResponse(json_encode([
            'sent' => $mail->isSent(),
            'recipientsHandled' => $mail->getNumberOfRecipientsHandled(),
            'deliveryProgress' => $mail->getDeliveryProgress(),
            'scheduledBegin' => $mail->getScheduledBegin() ? $mail->getScheduledBegin()->format('d.m.Y H:i') : '',
            'scheduledEnd' => $mail->getScheduledEnd() ? $mail->getScheduledEnd()->format('d.m.Y H:i') : '',
        ]));
    }

    /**
     * Create document header buttons of "overview" action
     *
     * @param string $requestUri
     * @param bool $showConfigurationButton
     */
    protected function configureOverViewDocHeader(string $requestUri, bool $showConfigurationButton = false): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        if ($showConfigurationButton) {
            $configurationButton = $buttonBar->makeInputButton()
                ->setTitle(LanguageUtility::getLL('general.button.configuration'))
                ->setName('configure')
                ->setDataAttributes([
                    'bs-toggle' => 'modal',
                    'bs-target' => '#mail-queue-configuration-modal',
                    'modal-identifier' => 'mail-queue-configuration-modal',
                    'modal-title' => LanguageUtility::getLL('queue.button.configuration'),
                    'button-ok-text' => LanguageUtility::getLL('general.button.save'),
                    'button-close-text' => LanguageUtility::getLL('general.button.cancel')
                ])
                ->setClasses('js-mail-queue-configuration-modal')
                ->setValue(1)
                ->setIcon($this->iconFactory->getIcon('actions-cog-alt', Icon::SIZE_SMALL));
            $buttonBar->addButton($configurationButton, ButtonBar::BUTTON_POSITION_RIGHT, 1);
        }

        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($requestUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT, 2);

        $routeIdentifier = $this->typo3MajorVersion < 12 ? 'MailMail_MailQueue' : 'mail_queue';
        $shortCutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier($routeIdentifier)
            ->setDisplayName(LanguageUtility::getLL('shortcut.queue') . ' [' . $this->id . ']')
            ->setArguments([
                'id' => $this->id,
            ]);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT, 3);
    }
}
