<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailerEngineController extends AbstractController
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
    protected string $moduleName = 'MailNavFrame_Status';

    protected function init(ServerRequestInterface $request): void
    {
        parent::init($request);

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->invokeMailerEngine = (bool)($queryParams['invokeMailerEngine'] ?? false);
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->init($request);

        if ($this->backendUserHasModuleAccess() === false) {
            $this->view->setTemplate('NoAccess');
            $this->messageQueue->addMessage(ViewUtility::getFlashMessage('If no access or if ID == zero', 'No Access', AbstractMessage::WARNING));
            $this->moduleTemplate->setContent($this->view->render());
            return new HtmlResponse($this->moduleTemplate->renderContent());
        }

        $this->view->setTemplate('MailerEngine');

        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            if ($this->action == 'delete' && $this->uid) {
                $this->deleteDMail($this->uid);
            }

            // Direct mail module
            if (($this->pageInfo['doktype'] ?? 0) == 254) {
                $mailerEngine = $this->mailerengine();

                $this->view->assignMultiple(
                    [
                        'data' => $mailerEngine['data'],
                        'id' => $this->id,
                        'invoke' => $mailerEngine['invoke'],
                        'moduleName' => $this->moduleName,
                        'moduleUrl' => $mailerEngine['moduleUrl'],
                        'show' => true,
                    ]
                );
            } else {
                if ($this->id != 0) {
                    $message = ViewUtility::getFlashMessage(LanguageUtility::getLL('dmail_noRegular'), LanguageUtility::getLL('dmail_newsletters'),
                        AbstractMessage::WARNING);
                    $this->messageQueue->addMessage($message);
                }
            }
        } else {
            $message = ViewUtility::getFlashMessage(LanguageUtility::getLL('select_folder'), LanguageUtility::getLL('header_mailer'),
                AbstractMessage::WARNING);
            $this->messageQueue->addMessage($message);
        }

        // Render template and return html content
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return array List of the mailing status
     * @throws DBALException
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function mailerengine(): array
    {
        $invoke = false;
        $moduleUrl = '';

        // enable manual invocation of mailer engine; enabled by default
        $enableTrigger = !(isset($this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);

        if ($enableTrigger && $this->invokeMailerEngine) {
            $this->invokeMEngine();
            ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_mailerengine_invoked'));
        }

        // Invoke engine
        if ($enableTrigger) {
            $moduleUrl = $this->buildUriFromRoute(
                'MailNavFrame_Status',
                [
                    'id' => $this->id,
                    'invokeMailerEngine' => 1,
                ]
            );

            $invoke = true;
        }

        $data = [];
        $sysDmailRepository = GeneralUtility::makeInstance(SysDmailRepository::class);
        $rows = $sysDmailRepository->findScheduledByPid($this->id);
        if (is_array($rows)) {
            $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
            foreach ($rows as $row) {
                $sent = $sysDmailMaillogRepository->countByUid($row['uid']);
                [$percentOfSent, $numberOfRecipients] = MailerUtility::calculatePercentOfSend($sent, (int)$row['recipients']);

                $data[] = [
                    'uid' => $row['uid'],
                    'subject' => $this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'], 100)), $row['uid']),
                    'scheduled' => BackendUtility::datetime($row['scheduled']),
                    'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                    'scheduled_end' => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                    'sent' => $sent,
                    'numberOfRecipients' => $numberOfRecipients,
                    'percentOfSent' => $percentOfSent,
                    'delete' => $this->canDelete($row['uid']),
                ];
            }
        }
        unset($rows);

        return ['invoke' => $invoke, 'moduleUrl' => $moduleUrl, 'data' => $data];
    }

    /**
     * Checks if the record can be deleted
     *
     * @param int $uid Uid of the record
     * @return bool
     */
    public function canDelete(int $uid): bool
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);

        // show delete icon if newsletter hasn't been sent, or not yet finished sending
        return ($dmail['scheduled_begin'] === 0 || $dmail['scheduled_end'] === 0);
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
                $table, // table
                [$GLOBALS['TCA'][$table]['ctrl']['delete'] => 1],
                ['uid' => $uid] // where
            );
        }
    }

    /**
     * Invoking the mail engine
     * This method no longer returns logs in backend modul directly
     *
     * @see        Dmailer::start
     * @see        Dmailer::runcron
     */
    public function invokeMEngine()
    {
        $this->mailerService->start();
        $this->mailerService->runcron();
    }

    /**
     * Wrapping a string with a link
     *
     * @param string $str String to be wrapped
     * @param int $uid Uid of the record
     *
     * @return string wrapped string as a link
     */
    public function linkDMail_record(string $str, int $uid): string
    {
        return $str;
        //TODO: Link to detail page for the new queue
        #return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
    }
}
