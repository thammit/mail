<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Http\HtmlResponse;

class QueueController extends AbstractController
{
    /**
     * for cmd == 'delete'
     * @var integer
     */
    protected int $uid = 0;

    protected bool $invokeMailerEngine = false;

    /**
     * The name of the module
     *
     * @var string
     */
    protected string $moduleName = 'Mail_Status';

    protected function init(ServerRequestInterface $request): void
    {
        parent::init($request);

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->invokeMailerEngine = (bool)($queryParams['invokeMailerEngine'] ?? false);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->init($request);

        if ($this->backendUserHasModuleAccess() === false) {
            $this->view->setTemplate('NoAccess');
            ViewUtility::addWarningToFlashMessageQueue('If no access or if ID == zero', 'No Access');
            $this->moduleTemplate->setContent($this->view->render());
            return new HtmlResponse($this->moduleTemplate->renderContent());
        }

        $this->view->setTemplate('Queue');

        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            if ($this->action->equals(Action::DELETE_MAIL) && $this->uid) {
                $this->deleteDMail($this->uid);
            }

            if (($this->pageInfo['doktype'] ?? 0) == 254) {

                $enableManualTrigger = !(isset($this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);
                if ($enableManualTrigger && $this->invokeMailerEngine) {
                    $this->mailerService->start();
                    $this->mailerService->handleQueue();
                    ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_mailerengine_invoked'));
                }

                $this->view->assignMultiple(
                    [
                        'data' => $this->getModuleData(),
                        'id' => $this->id,
                        'enableManualTrigger' => $enableManualTrigger,
                        'route' => $this->moduleName,
                    ]
                );
            } else {
                if ($this->id != 0) {
                    ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_noRegular'), LanguageUtility::getLL('dmail_newsletters'));
                }
            }
        } else {
            ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('select_folder'), LanguageUtility::getLL('header_mailer'));
        }

        // Render template and return html content
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @return array List of the mailing status
     * @throws DBALException
     * @throws Exception
     */
    public function getModuleData(): array
    {
        $data = [];
        $rows = $this->sysDmailRepository->findScheduledByPid($this->id);
        foreach ($rows as $row) {
            $sent = $this->sysDmailMaillogRepository->countByUid($row['uid']);
            [$percentOfSent, $numberOfRecipients] = MailerUtility::calculatePercentOfSend($sent, (int)$row['recipients']);

            $data[] = [
                'uid' => $row['uid'],
                'subject' => $row['subject'],
                'scheduled' => BackendUtility::datetime($row['scheduled']),
                'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                'scheduled_end' => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                'sent' => $sent,
                'numberOfRecipients' => $numberOfRecipients,
                'percentOfSent' => $percentOfSent,
                'delete' => $this->canDelete($row['uid']),
            ];
        }

        return $data;
    }

    /**
     * Checks if the record can be deleted
     *
     * @param int $uid Uid of the record
     * @return bool
     */
    public function canDelete(int $uid): bool
    {
        $mail = BackendUtility::getRecord('sys_dmail', $uid);

        // show delete icon if newsletter hasn't been sent, or not yet finished sending
        return ($mail['scheduled_begin'] === 0 || $mail['scheduled_end'] === 0);
    }

    /**
     * Delete existing dmail record
     *
     * @param int $uid Record uid to be deleted
     *
     * @return void
     */
    public function deleteDMail(int $uid): void
    {
        $table = 'sys_dmail';
        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            $connection = $this->getConnection($table);

            $connection->update(
                $table,
                [$GLOBALS['TCA'][$table]['ctrl']['delete'] => 1],
                ['uid' => $uid]
            );
        }
    }
}
