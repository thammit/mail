<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        // todo replace with extbase repository
        $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        $scheduledMails = $this->mailRepository->findScheduledByPid($this->id)->toArray();
        /** @var Mail $mail */
        foreach ($scheduledMails as $mail) {
            $sent = $sysDmailMaillogRepository->countByUid($mail->getUid());
            [$percentOfSent, $numberOfRecipients] = MailerUtility::calculatePercentOfSend($sent, $mail->getRecipients());

            $data[] = [
                'mail' => $mail,
                'sent' => $sent,
                'numberOfRecipients' => $numberOfRecipients,
                'percentOfSent' => $percentOfSent,
                'delete' => $mail->getScheduledBegin() === 0 || $mail->getScheduledEnd() === 0,
            ];
        }
        $this->view->assignMultiple([
            'id' => $this->id,
            'data' => $data,
            'trigger' => !(isset($this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger'])
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @return void
     * @throws StopActionException
     * @throws DBALException
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function triggerAction(): void
    {
        $this->mailerService->start();
        $this->mailerService->handleQueue();
        ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_mailerengine_invoked'), true);
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
        $this->redirect('index');
    }
}
