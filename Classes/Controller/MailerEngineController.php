<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
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
                $cronMonitor = $this->cronMonitor();
                $mailerEngine = $this->mailerengine();

                $this->view->assignMultiple(
                    [
                        'cronMonitor' => $cronMonitor,
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
     * Monitor the cronjob.
     *
     * @return void        status of the cronjob in HTML Tableformat
     */
    public function cronMonitor(): void
    {
        $mailerStatus = 0;
        $lastExecutionTime = 0;
        $logContent = '';
        $error = '';

        // seconds
//        $cronInterval = MailerUtility::getExtensionConfiguration('cronInt') * 60;
        $cronInterval = 60 * 60;
        $lastCronjobShouldBeNewThan = (time() - $cronInterval);
        $filename = $this->getDmailerLogFilePath();
        if (file_exists($filename)) {
            $logContent = file_get_contents($filename);
            $lastExecutionTime = substr($logContent, 0, 10);
        }

        /*
         * status:
         * 	1 = ok
         * 	0 = check
         * 	-1 = cron stopped
         *
         * cron running or error (die function in dmailer_log)
         */
        if (file_exists($this->getDmailerLockFilePath())) {
            $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findByResponseType(0);
            if (is_array($res)) {
                foreach ($res as $lastSend) {
                    if (($lastSend['tstamp'] < time()) && ($lastSend['tstamp'] > $lastCronjobShouldBeNewThan)) {
                        // cron is sending
                        $mailerStatus = 1;
                    } else {
                        // there's lock file but cron is not sending
                        $mailerStatus = -1;
                    }
                }
            }
            // cron is idle or no cron
        } else {
            if (strpos($logContent, 'error')) {
                // error in log file
                $mailerStatus = -1;
                $error = substr($logContent, strpos($logContent, 'error') + 7);
            } else {
                if (!strlen($logContent) || ($lastExecutionTime < $lastCronjobShouldBeNewThan)) {
                    // cron is not set or not running
                    $mailerStatus = 0;
                } else {
                    // last run of cron is in the interval
                    $mailerStatus = 1;
                }
            }
        }

        $currentDate = ' / ' . LanguageUtility::getLL('dmail_mailerengine_current_time') . ' ' . BackendUtility::datetime(time()) . '. ';
        $lastRun = ' ' . LanguageUtility::getLL('dmail_mailerengine_cron_lastrun') . ($lastExecutionTime ? BackendUtility::datetime($lastExecutionTime) : '-') . $currentDate;
        switch ($mailerStatus) {
            case -1:
                $message = ViewUtility::getFlashMessage(
                    LanguageUtility::getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : LanguageUtility::getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
                    LanguageUtility::getLL('dmail_mailerengine_cron_status'),
                    AbstractMessage::ERROR
                );
                $this->messageQueue->addMessage($message);
                break;
            case 0:
                $message = ViewUtility::getFlashMessage(
                    LanguageUtility::getLL('dmail_mailerengine_cron_caution') . ': ' . LanguageUtility::getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
                    LanguageUtility::getLL('dmail_mailerengine_cron_status'),
                    AbstractMessage::WARNING
                );
                $this->messageQueue->addMessage($message);
                break;
            case 1:
                $message = ViewUtility::getFlashMessage(
                    LanguageUtility::getLL('dmail_mailerengine_cron_ok') . ': ' . LanguageUtility::getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
                    LanguageUtility::getLL('dmail_mailerengine_cron_status'),
                    AbstractMessage::OK
                );
                $this->messageQueue->addMessage($message);
                break;
            default:
        }
    }

    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return array|string List of the mailing status
     * @throws DBALException
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function mailerengine(): array|string
    {
        $invoke = false;
        $moduleUrl = '';

        // enable manual invocation of mailer engine; enabled by default
        $enableTrigger = !(isset($this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->pageTSConfiguration['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);

        if ($enableTrigger && $this->invokeMailerEngine) {
            $this->invokeMEngine();
            $message = ViewUtility::getFlashMessage('', LanguageUtility::getLL('dmail_mailerengine_invoked'), AbstractMessage::INFO);
            $this->messageQueue->addMessage($message);
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
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->findScheduledByPid($this->id);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $data[] = [
                    'uid' => $row['uid'],
                    'icon' => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                    'subject' => $this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'], 100)), $row['uid']),
                    'scheduled' => BackendUtility::datetime($row['scheduled']),
                    'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                    'scheduled_end' => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                    'sent' => GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countByUid($row['uid']),
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
