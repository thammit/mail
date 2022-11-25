<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class QueueController extends AbstractController
{
    /**
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function indexAction(): ResponseInterface
    {
        $data = [];
        $scheduledMails = $this->mailRepository->findScheduledByPid($this->id)->toArray();
        /** @var Mail $mail */
        foreach ($scheduledMails as $mail) {
            $sent = $this->logRepository->countByMailUid($mail->getUid());

            $data[] = [
                'mail' => $mail,
                'sent' => $sent,
                'percentOfSent' => MailerUtility::calculatePercentOfSend($sent, $mail->getNumberOfRecipients()),
                'delete' => !$mail->getScheduledBegin() || !$mail->getScheduledEnd(),
            ];
        }
        $this->view->assignMultiple([
            'id' => $this->id,
            'data' => $data,
            'sendPerCycle' => (int)($this->pageTSConfiguration['sendPerCycle'] ?? 50),
            'trigger' => !(isset($this->pageTSConfiguration['menu.']['mail.']['queue.']['disable_trigger']) && $this->pageTSConfiguration['menu.']['mail.']['queue.']['disable_trigger'])
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->configureOverViewDocHeader($this->request->getRequestTarget());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @return void
     * @throws StopActionException
     * @throws DBALException
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function triggerAction(): void
    {
        if (!$this->mailRepository->findMailToSend()) {
            ViewUtility::addNotificationInfo(
                LanguageUtility::getLL('queue.notification.nothingToDo.message'),
                LanguageUtility::getLL('queue.notification.nothingToDo.title')
            );
            $this->redirect('index');
        }
        $this->mailerService->start((int)($this->pageTSConfiguration['sendPerCycle'] ?? 50));
        $this->mailerService->handleQueue();
        ViewUtility::addNotificationSuccess(
            LanguageUtility::getLL('queue.notification.mailSendTriggered.message'),
            LanguageUtility::getLL('mail.wizard.notification.severity.success.title')
        );
        $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(Mail $mail):void
    {
        $this->mailRepository->remove($mail);
        ViewUtility::addNotificationSuccess(
            LanguageUtility::getLL('queue.notification.missingRecipientGroup.message'),
            LanguageUtility::getLL('mail.wizard.notification.severity.success.title')
        );
        $this->redirect('index');
    }

    /**
     * Create document header buttons of "overview" action
     *
     * @param string $requestUri
     */
    protected function configureOverViewDocHeader(string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($requestUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
