<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Model\MailFactory;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\RepositoryUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class MailController extends AbstractController
{
    protected string $route = 'Mail_Mail';
    protected string $moduleName = 'Mail_Mail';
    protected string $cshKey = '_MOD_Mail_Mail';
    protected int $currentStep = 1;
    protected bool $reset = false;
    protected Action $currentCmd;
    protected bool $backButtonPressed = false;

    protected int $uid = 0;
    protected int $createMailFromPageUid = 0;
    protected int $createMailForLanguageUid = 0;
    protected array $external = [];
    protected array $quickMail = [];
    protected bool $isOpen = false;
    protected bool $isInternal = false;
    protected bool $isExternal = false;
    protected bool $isQuickMail = false;
    protected array $mailGroupUids = [];
    protected bool $sendTestMail = false;
    protected bool $scheduleSendAll = false;
    protected bool $saveDraft = false;
    protected bool $isTestMail = false;
    protected string $sendTestMailAddress = '';
    protected int $distributionTimeStamp = 0;
    protected string $requestUri = '';

    /**
     * Init module
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    protected function init(ServerRequestInterface $request): void
    {
        parent::init($request);

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestUri = $normalizedParams->getRequestUri();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);

        if ($parsedBody['update_cats'] ?? $queryParams['update_cats'] ?? false) {
            $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_CATEGORIES));
        }

        $this->sendTestMail = (bool)($parsedBody['sendTestMail']['send'] ?? $queryParams['sendTestMail']['send'] ?? false);
        $this->sendTestMailAddress = (string)($parsedBody['sendTestMail']['address'] ?? $queryParams['sendTestMail']['address'] ?? '');
        if ($this->sendTestMail) {
            $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_SEND_TEST2));
        }

        $this->backButtonPressed = (bool)($parsedBody['back'] ?? $queryParams['back'] ?? false);

        $this->currentCmd = Action::cast(($parsedBody['currentCmd'] ?? $queryParams['currentCmd'] ?? null));

        $this->isOpen = (bool)$this->mailUid;

        $this->createMailFromPageUid = (int)($parsedBody['createMailFromPageUid'] ?? $queryParams['createMailFromPageUid'] ?? 0);
        $this->createMailForLanguageUid = (int)($parsedBody['createMailForLanguageUid'] ?? $queryParams['createMailForLanguageUid'] ?? 0);
        $this->isInternal = (bool)$this->createMailFromPageUid;

        $this->external = (array)($parsedBody['external'] ?? $queryParams['external'] ?? []);
        $this->isExternal = (bool)($this->external['send'] ?? false);

        $this->quickMail = (array)($parsedBody['quickmail'] ?? $queryParams['quickmail'] ?? []);
        $this->isQuickMail = (bool)($this->quickMail['send'] ?? false);

        $this->mailGroupUids = $parsedBody['mailGroupUid'] ?? $queryParams['mailGroupUid'] ?? [];
        $this->scheduleSendAll = (bool)($parsedBody['scheduleSendAll'] ?? $queryParams['scheduleSendAll'] ?? false);
        $this->saveDraft = (bool)($parsedBody['saveDraft'] ?? $queryParams['saveDraft'] ?? false);
        $this->isTestMail = (bool)($parsedBody['isTestMail'] ?? $queryParams['isTestMail'] ?? false);
        $this->distributionTimeStamp = strtotime((string)($parsedBody['distributionTime'] ?? $queryParams['distributionTime'] ?? '')) ?: time();
        if ($this->distributionTimeStamp < time()) {
            $this->distributionTimeStamp = time();
        }
        $this->view->assign('settings', [
            'route' => $this->route,
            'mailSysFolderUid' => $this->id,
            'steps' => [
                'overview' => Action::WIZARD_STEP_OVERVIEW,
                'settings' => Action::WIZARD_STEP_SETTINGS,
                'categories' => Action::WIZARD_STEP_CATEGORIES,
                'sendTest' => Action::WIZARD_STEP_SEND_TEST,
                'sendTest2' => Action::WIZARD_STEP_SEND_TEST2,
                'final' => Action::WIZARD_STEP_FINAL,
                'send' => Action::WIZARD_STEP_SEND,
            ],
        ]);
    }

    /**
     * Handle module request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws RouteNotFoundException
     * @throws SiteNotFoundException
     * @throws TransportExceptionInterface
     * @throws \Doctrine\DBAL\Exception
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

        $this->view->setTemplate('Mail/Index');

        // get module name of the selected page in the page tree
        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            // mail module
            if (($this->pageInfo['doktype'] ?? 0) === 254) {
                // Add module data to view
                $this->view->assignMultiple($this->getModuleData());
                if ($this->reset) {
                    return new RedirectResponse($this->uriBuilder->buildUriFromRoute($this->route, ['id' => $this->id]));
                }
            } else {
                if ($this->id) {
                    // a subpage of a mail folder is selected -> redirect user to settings page
                    $openMails = $this->sysDmailRepository->findOpenMailsByPageId($this->pageInfo['pid']);
                    foreach ($openMails as $openMail) {
                        if ($openMail['page'] === $this->id) {
                            // there is already an open mail of this page -> use it
                            $uri = $this->uriBuilder->buildUriFromRoute(
                                'Mail_Mail',
                                [
                                    'id' => $this->pageInfo['pid'],
                                    'cmd' => Action::WIZARD_STEP_SETTINGS,
                                    'mailUid' => $openMail['uid'],
                                ]
                            );
                            return new RedirectResponse($uri);
                        }
                    }
                    // create a new mail of the page
                    $uri = $this->uriBuilder->buildUriFromRoute(
                        'Mail_Mail',
                        [
                            'id' => $this->pageInfo['pid'],
                            'cmd' => Action::WIZARD_STEP_SETTINGS,
                            'createMailFromPageUid' => $this->id,
                        ]
                    );
                    return new RedirectResponse($uri);
                }
            }
        } else {
            ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('select_folder'), LanguageUtility::getLL('header_directmail'));
        }

        // Render template and return html content
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Get module data
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws RouteNotFoundException
     * @throws SiteNotFoundException
     * @throws TransportExceptionInterface
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function getModuleData(): array
    {
        if ($this->action->equals(Action::DELETE_MAIL)) {
            $this->sysDmailRepository->delete($this->uid);
        }

        $isExternalDirectMailRecord = false;
        $mailData = [];
        if ($this->mailUid) {
            // get the data of the currently selected mail
            $mailData = $this->sysDmailRepository->findByUid($this->mailUid);
            $isExternalDirectMailRecord = is_array($mailData) && (int)$mailData['type'] === MailType::EXTERNAL;
        }

        $userTSConfig = TypoScriptUtility::getUserTSConfig();
        $hideCategoryStep = $isExternalDirectMailRecord || (isset($userTSConfig['tx_directmail.']['hideSteps']) && $userTSConfig['tx_directmail.']['hideSteps'] === 'cat');

        if ($this->backButtonPressed) {
            // CMD move 1 step back
            switch ((string)$this->currentCmd) {
                case Action::WIZARD_STEP_SETTINGS:
                    $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_OVERVIEW));
                    break;
                case Action::WIZARD_STEP_CATEGORIES:
                    $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_SETTINGS));
                    break;
                case Action::WIZARD_STEP_SEND_TEST:
                    // Same as send_mail_test
                case Action::WIZARD_STEP_SEND_TEST2:
                    if ($this->action->equals(Action::WIZARD_STEP_SEND) && $hideCategoryStep) {
                        $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_SETTINGS));
                    } else {
                        $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_CATEGORIES));
                    }
                    break;
                case Action::WIZARD_STEP_FINAL:
                    // The same as send_mass
                case Action::WIZARD_STEP_SEND:
                    $this->setCurrentAction(Action::cast(Action::WIZARD_STEP_SEND_TEST));
                    break;
                default:
                    // Do nothing
            }
        }

        $nextCmd = '';
        if ($hideCategoryStep) {
            $totalSteps = 4;
            if ($this->action->equals(Action::WIZARD_STEP_SETTINGS)) {
                $nextCmd = Action::WIZARD_STEP_SEND_TEST;
            }
        } else {
            $totalSteps = 5;
            if ($this->action->equals(Action::WIZARD_STEP_SETTINGS)) {
                $nextCmd = Action::WIZARD_STEP_CATEGORIES;
            }
        }

        $moduleData = [
            'navigation' => [
                'back' => false,
                'next' => false,
                'nextError' => false,
                'totalSteps' => $totalSteps,
                'currentStep' => 1,
                'steps' => range(1, $totalSteps),
            ],
        ];

        switch ((string)$this->getCurrentAction()) {
            case Action::WIZARD_STEP_SETTINGS:

                // step 2: create mail record or use existing

                $this->currentStep = 2;
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['info'] = [
                    'currentStep' => $this->currentStep,
                ];

                // greyed out next-button if fetching is not successful (on error)
                $fetchError = false;

                $mailFactory = MailFactory::forStorageFolder($this->id);
                $moduleData['info']['nextCmd'] = Action::WIZARD_STEP_SEND_TEST;

                switch (true) {
                    case $this->isInternal:
                        // create mail from internal page
                        $newMail = $mailFactory->fromInternalPage($this->createMailFromPageUid, $this->createMailForLanguageUid);
                        if ($newMail instanceof Mail) {
                            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                            $persistenceManager->add($newMail);
                            $persistenceManager->persistAll();
                            $newUid = $newMail->getUid();
                            $this->mailUid = $newUid;
                            // Read new record (necessary because TCEmain sets default field values)
                            $mailData = $this->sysDmailRepository->findByUid($newUid);
                            $moduleData['info']['nextCmd'] = $nextCmd ?: Action::WIZARD_STEP_CATEGORIES;
                        } else {
                            ViewUtility::addErrorToFlashMessageQueue('Error while adding the DB set', LanguageUtility::getLL('dmail_error'));
                            $fetchError = true;
                        }
                        break;
                    case $this->isExternal:
                        $newMail = $mailFactory->fromExternalUrls((string)($this->external['subject'] ?? ''), (string)($this->external['htmlUri'] ?? ''), (string)($this->external['plainUri'] ?? ''));
                        if ($newMail instanceof Mail) {
                            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                            $persistenceManager->add($newMail);
                            $persistenceManager->persistAll();
                            $newUid = $newMail->getUid();
                            $this->mailUid = $newUid;
                            // Read new record (necessary because TCEmain sets default field values)
                            $mailData = $this->sysDmailRepository->findByUid($newUid);
                        } else {
                            $fetchError = true;
                            ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_html_uri_is_invalid') . ' Requested URL: ' . $this->external['htmlUri'],
                                LanguageUtility::getLL('dmail_error'));
                        }
                        break;
                    case $this->isQuickMail:
                        $subject = (string)($this->quickMail['subject'] ?? '');
                        $message = (string)($this->quickMail['message'] ?? '');
                        $senderName = (string)($this->quickMail['senderName'] ?? '');
                        $senderEmail = (string)($this->quickMail['senderEmail'] ?? '');
                        $breakLines = (bool)($this->quickMail['breakLines'] ?? false);
                        $newMail = $mailFactory->fromText($subject, $message, $senderName, $senderEmail, $breakLines);
                        if ($newMail instanceof Mail) {
                            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                            $persistenceManager->add($newMail);
                            $persistenceManager->persistAll();
                            $newUid = $newMail->getUid();
                            $this->mailUid = $newUid;
                            // Read new record (necessary because TCEmain sets default field values)
                            $mailData = $this->sysDmailRepository->findByUid($newUid);
                        } else {
                            $fetchError = true;
                        }
                        break;
                    case $this->isOpen:
                        if ($mailData) {
                            if ($mailData['type'] === MailType::EXTERNAL) {
                                // it's a quick/external mail
                                if (str_starts_with($mailData['HTMLParams'], 'http') || str_starts_with($mailData['plainParams'], 'http')) {
                                    // it's an external mail -> fetch content again
                                    $newMail = $mailFactory->fromExternalUrls($mailData['subject'], $mailData['HTMLParams'], $mailData['plainParams']);
                                    if ($newMail instanceof Mail) {
                                        // copy new fetch content and charset to current mail record
                                        // todo use extbase
                                        $this->sysDmailRepository->update($mailData['uid'], [
                                            'mailContent' => $newMail->getMailContent(),
                                            'renderedSize' => $newMail->getRenderedSize(),
                                            'charset' => $newMail->getCharset()
                                        ]);
                                        // Read new record (necessary because TCEmain sets default field values)
                                        $mailData = $this->sysDmailRepository->findByUid($mailData['uid']);
                                    } else {
                                        $fetchError = true;
                                    }
                                }
                            } else {
                                $newMail = $mailFactory->fromInternalPage($mailData['page'], $mailData['sys_language_uid']);
                                if ($newMail instanceof Mail) {
                                    // copy new fetch content and charset to current mail record
                                    // todo use extbase
                                    $this->sysDmailRepository->update($mailData['uid'], [
                                        'mailContent' => $newMail->getMailContent(),
                                        'renderedSize' => $newMail->getRenderedSize(),
                                    ]);
                                    // Read new record (necessary because TCEmain sets default field values)
                                    $mailData = $this->sysDmailRepository->findByUid($mailData['uid']);
                                } else {
                                    $fetchError = true;
                                }
                                $moduleData['info']['nextCmd'] = ($mailData['type'] === MailType::INTERNAL) ? $nextCmd : Action::WIZARD_STEP_SEND_TEST;
                            }
                        }
                        break;
                    default:
                }

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;
                $moduleData['navigation']['nextError'] = $fetchError;

                if (!$fetchError && !$this->isQuickMail) {
                    ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_wiz2_fetch_success'));
                }
                $moduleData['info']['table'] = is_array($mailData) ? $this->getGroupedMailSettings($mailData) : '';
                $moduleData['info']['mailUid'] = $this->mailUid;
                $moduleData['info']['pageUid'] = $mailData['page'] ?: '';
                $moduleData['info']['currentCmd'] = (string)$this->getCurrentAction();
                break;

            case Action::WIZARD_STEP_CATEGORIES:
                // shows category if content-based cat
                $this->currentStep = 3;
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['cats'] = [
                    'currentStep' => $this->currentStep,
                ];

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;

                $indata = GeneralUtility::_GP('indata') ?? [];
                $moduleData['cats']['output'] = $this->getCategoryData($mailData, $indata);

                $moduleData['cats']['cmd'] = Action::WIZARD_STEP_SEND_TEST;
                $moduleData['cats']['mailUid'] = $this->mailUid;
                $moduleData['cats']['pageUid'] = $this->pageUid;
                $moduleData['cats']['currentCmd'] = (string)$this->getCurrentAction();
                break;

            case Action::WIZARD_STEP_SEND_TEST:
                // Same as send_mail_test
            case Action::WIZARD_STEP_SEND_TEST2:
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['test'] = [
                    'currentStep' => $this->currentStep,
                ];

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;

                if ($this->action->equals(Action::WIZARD_STEP_SEND_TEST2)) {
                    $this->sendSimpleTestMail($mailData);
                }
                $moduleData['test']['testFormData'] = $this->getTestMailConfig();
                $moduleData['test']['cmd'] = Action::WIZARD_STEP_SEND;
                $moduleData['test']['mailUid'] = $this->mailUid;
                $moduleData['test']['pageUid'] = $this->pageUid;
                $moduleData['test']['currentCmd'] = (string)$this->getCurrentAction();
                break;

            case Action::WIZARD_STEP_FINAL:
                // same as send_mass
            case Action::WIZARD_STEP_SEND:
                $this->currentStep = 5 - (5 - $totalSteps);
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['final'] = ['currentStep' => $this->currentStep];

                $moduleData['navigation']['back'] = $this->action->equals(Action::WIZARD_STEP_SEND);

                if ($this->action->equals(Action::WIZARD_STEP_FINAL)) {
                    if (count($this->mailGroupUids)) {
                        if ($this->isTestMail) {
                            $this->sendPersonalizedTestMails($mailData);
                        } else {
                            $this->schedulePersonalizedMails($mailData);
                            // todo jump to overview page
                            $moduleData = $this->getOverviewModuleData($moduleData);
                            $this->reset = true;
                            break;
                        }
                        // break;
                    } else {
                        ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('mod.no_recipients'));
                    }
                }
                // send mass, show calendar
                $moduleData['final']['finalForm'] = $this->getFinalData($mailData);
                $moduleData['final']['cmd'] = Action::WIZARD_STEP_FINAL;
                $moduleData['final']['id'] = $this->id;
                $moduleData['final']['mailUid'] = $this->mailUid;
                $moduleData['final']['pageUid'] = $this->pageUid;
                $moduleData['final']['currentCmd'] = (string)$this->getCurrentAction();
                break;

            case Action::WIZARD_STEP_OVERVIEW:
            default:
                $moduleData = $this->getOverviewModuleData($moduleData);
        }

        return $moduleData;
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    protected function getOverviewModuleData($moduleData): array
    {
        // choose source newsletter
        $this->currentStep = 1;
        $moduleData['navigation']['currentStep'] = $this->currentStep;

        $panels = [Constants::PANEL_INTERNAL, Constants::PANEL_EXTERNAL, Constants::PANEL_QUICK_MAIL, Constants::PANEL_OPEN];
        if (isset($userTSConfig['tx_directmail.']['hideTabs'])) {
            $hidePanel = GeneralUtility::trimExplode(',', $userTSConfig['tx_directmail.']['hideTabs']);
            foreach ($hidePanel as $hideTab) {
                $panels = ArrayUtility::removeArrayEntryByValue($panels, $hideTab);
            }
        }
        if (!isset($userTSConfig['tx_directmail.']['defaultTab'])) {
            $userTSConfig['tx_directmail.']['defaultTab'] = Constants::PANEL_OPEN;
        }

        foreach ($panels as $panel) {
            $open = $userTSConfig['tx_directmail.']['defaultTab'] == $panel;
            switch ($panel) {
                case Constants::PANEL_OPEN:
                    $moduleData['default']['open'] = [
                        'open' => $open,
                        'data' => $this->sysDmailRepository->findOpenMailsByPageId($this->id)
                    ];
                    break;
                case Constants::PANEL_INTERNAL:
                    $moduleData['default']['internal'] = [
                        'open' => $open,
                        'data' => $this->pagesRepository->findMailPages($this->id, $this->backendUserPermissions)
                    ];
                    break;
                case Constants::PANEL_EXTERNAL:
                    $moduleData['default']['external'] = ['open' => $open];
                    break;
                case Constants::PANEL_QUICK_MAIL:
                    $moduleData['default']['quickMail'] = [
                        'open' => $open,
                        'id' => $this->id,
                        'senderName' => BackendUserUtility::getBackendUser()->user['realName'],
                        'senderEmail' => BackendUserUtility::getBackendUser()->user['email'],
                        'subject' => '',
                        'message' => '',
                        'breakLines' => '',
                    ];
                    break;
                default:
            }
        }
        return $moduleData;
    }

    /**
     * Shows the infos of a directmail record in a table
     *
     * @param array $mailData DirectMail DB record
     *
     * @return array
     */
    protected function getGroupedMailSettings(array $mailData): array
    {
        $tableRows = [];

        $groups = [
            'composition' => ['type', 'sys_language_uid', 'page', 'plainParams', 'HTMLParams', 'attachment', 'renderedsize'],
            'headers' => ['subject', 'from_email', 'from_name', 'replyto_email', 'replyto_name', 'return_path', 'organisation', 'priority', 'encoding'],
            'sending' => ['sendOptions', 'includeMedia', 'flowedFormat', 'use_rdct', 'long_link_mode', 'authcode_fieldList'],
        ];

        foreach ($groups as $groupName => $tcaColumns) {
            foreach ($tcaColumns as $columnName) {
                if ($columnName === 'attachment') {
                    $fileNames = [];
                    $attachments = MailerUtility::getAttachments($mailData['uid'] ?? 0);
                    if (count($attachments)) {
                        /** @var FileReference $attachment */
                        foreach ($attachments as $attachment) {
                            $fileNames[] = $attachment->getName();
                        }
                    }
                    $tableRows[$groupName][] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField('attachment'),
                        'value' => implode(', ', $fileNames),
                    ];
                } else {
                    $tableRows[$groupName][] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField($columnName),
                        'value' => htmlspecialchars((string)BackendUtility::getProcessedValue('sys_dmail', $columnName, ($mailData[$columnName] ?? false))),
                    ];
                }
            }
        }

        return [
            'title' => htmlspecialchars($mailData['subject'] ?? ''),
            'tableRows' => $tableRows,
            'isSent' => isset($mailData['issent']) && $mailData['issent'],
            'allowEdit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'sys_dmail'),
        ];
    }

    /**
     * Show the categories table for user to categorize the directmail content
     * TYPO3 content
     *
     * @param array $mailData The dmail row.
     * @param array $indata
     *
     * @return array|string HTML form showing the categories
     * @throws DBALException
     * @throws Exception
     */
    protected function getCategoryData(array $mailData, array $indata): array|string
    {
        $categoryData = [
            'title' => LanguageUtility::getLL('nl_cat'),
            'rowsFound' => false,
            'rows' => [],
            'pageUid' => $this->pageUid,
            'update_cats' => LanguageUtility::getLL('nl_l_update'),
            'output' => '',
        ];

        if (isset($indata['categories']) && is_array($indata['categories'])) {
            $data = [];
            foreach ($indata['categories'] as $recUid => $recValues) {
                $enabled = [];
                foreach ($recValues as $k => $b) {
                    if ($b) {
                        $enabled[] = $k;
                    }
                }
                $data['tt_content'][$recUid]['module_sys_dmail_category'] = implode(',', $enabled);
            }

            $dataHandler = $this->getDataHandler();
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($this->pageUid);
            // todo check if the following line is needed since there is no change to $mailData made
            // $fetchError = !$this->mailerService->assemble($mailData, $this->pageTSConfiguration);
        }

        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->findByPidAndSysLanguageUid(
            $this->pageUid,
            (int)$mailData['sys_language_uid']
        );

        if (empty($rows)) {
            $categoryData['subtitle'] = LanguageUtility::getLL('nl_cat_msg1');
            return $categoryData;
        }
        $categoryData['subtitle'] = BackendUtility::cshItem($this->cshKey, 'assign_categories');
        $categoryData['rowsFound'] = true;

        $colPosVal = 99;
        $ttContentCategoryMmRepository = GeneralUtility::makeInstance(TtContentCategoryMmRepository::class);
        foreach ($rows as $contentElementData) {
            $categoriesRow = [];
            $resCat = $ttContentCategoryMmRepository->selectUidForeignByUid($contentElementData['uid']);

            foreach ($resCat as $rowCat) {
                $categoriesRow[] = (int)$rowCat['uid_foreign'];
            }

            if ($colPosVal != $contentElementData['colPos']) {
                $categoryData['rows'][] = [
                    'separator' => true,
                    'bgcolor' => '#f00',
                    'title' => LanguageUtility::getLL('nl_l_column'),
                    'value' => BackendUtility::getProcessedValue('tt_content', 'colPos', $contentElementData['colPos']),
                ];
                $colPosVal = $contentElementData['colPos'];
            }

            $ttContentCategories = RepositoryUtility::makeCategories('tt_content', $contentElementData, $this->sysLanguageUid);
            reset($ttContentCategories);
            $cboxes = [];
            foreach ($ttContentCategories as $pKey => $pVal) {
                $cboxes[] = [
                    'pKey' => $pKey,
                    'checked' => in_array((int)$pKey, $categoriesRow),
                    'pVal' => htmlspecialchars($pVal),
                ];
            }

            $categoryData['rows'][] = [
                'uid' => $contentElementData['uid'],
                'icon' => $this->iconFactory->getIconForRecord('tt_content', $contentElementData, Icon::SIZE_SMALL),
                'header' => $contentElementData['header'],
                'CType' => $contentElementData['CType'],
                'list_type' => $contentElementData['list_type'],
                'bodytext' => empty($contentElementData['bodytext']) ? '' : GeneralUtility::fixed_lgd_cs(strip_tags($contentElementData['bodytext']), 200),
                'color' => $contentElementData['module_sys_dmail_category'] ? 'red' : 'green',
                'labelOnlyAll' => $contentElementData['module_sys_dmail_category'] ? LanguageUtility::getLL('nl_l_ONLY') : LanguageUtility::getLL('nl_l_ALL'),
                'checkboxes' => $cboxes,
            ];
        }
        return $categoryData;
    }

    /**
     * Show the step of sending a test mail
     *
     * @return array config for form
     * @throws DBALException
     * @throws Exception
     */
    protected function getTestMailConfig(): array
    {
        $data = [
            'id' => $this->id,
            'cmd' => Action::WIZARD_STEP_SEND_TEST2,
            'mailUid' => $this->mailUid,
            'dmail_test_email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
            'test_tt_address' => '',
            'test_dmail_group_table' => [],
        ];

        if ($this->pageTSConfiguration['test_tt_address_uids'] ?? false) {
            $data['ttAddress'] = $this->ttAddressRepository->findByUids(GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_tt_address_uids'], true),
                $this->backendUserPermissions);
        }

        if ($this->pageTSConfiguration['test_dmail_group_uids'] ?? false) {
            $testMailGroups = $this->sysDmailGroupRepository->findByUids(GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_dmail_group_uids']),
                $this->backendUserPermissions);

            $data['mailGroups'] = [];

            if ($testMailGroups) {
                foreach ($testMailGroups as $testMailGroup) {
                    $data['mailGroups'][$testMailGroup['uid']]['title'] = $testMailGroup['title'];
                    $recipientGroups = $this->recipientService->getRecipientIdsOfMailGroups([$testMailGroup['uid']]);
                    foreach ($recipientGroups as $recipientGroup => $recipients) {
                        switch ($recipientGroup) {
                            case 'fe_users':
                                foreach ($recipients as $recipient) {
                                    $data['mailGroups'][$testMailGroup['uid']]['groups'][$recipientGroup][] = $this->feUsersRepository->findByUid($recipient, 'uid,name,email');
                                }
                                break;
                            case 'tt_address':
                                foreach ($recipients as $recipient) {
                                    $data['mailGroups'][$testMailGroup['uid']]['groups'][$recipientGroup][] = $this->ttAddressRepository->findByUid($recipient, 'uid,name,email');
                                }
                                break;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get data for the final step
     * Show recipient list and calendar library
     *
     * @param array $mailData
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getFinalData(array $mailData): array
    {
        /**
         * Hook for cmd_finalmail
         * insert a link to open extended importer
         */
        // Todo: Change to PSR-14 Event Dispatcher
        $hookContents = '';
//        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] ?? false)) {
//            $hookObjectsArr = [];
//            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] as $classRef) {
//                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
//            }
//            foreach ($hookObjectsArr as $hookObj) {
//                if (method_exists($hookObj, 'cmd_finalmail')) {
//                    $hookContents = $hookObj->cmd_finalmail($this);
//                    $hookSelectDisabled = $hookObj->selectDisabled;
//                }
//            }
//        }

        $mailGroups = RecipientUtility::finalSendingGroups($this->id, (int)$mailData['sys_language_uid'], $this->userTable, $this->backendUserPermissions);

        return [
            'mailGroups' => $mailGroups,
            'hookContents' => $hookContents,
        ];
    }

    /**
     * Send a test mail to one or more email addresses
     *
     * @param array $row mail DB record
     *
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function sendSimpleTestMail(array $row): void
    {
        $this->mailerService->start();
        $this->mailerService->prepare($row);

        if ($this->sendTestMail) {
            $this->mailerService->setTestMail(true);

            // normalize addresses:
            $addressList = RecipientUtility::normalizeListOfEmailAddresses($this->sendTestMailAddress);

            if ($addressList) {
                // Sending the same mail to lots of recipients
                $this->mailerService->sendSimpleMail($addressList);
                ViewUtility::addOkToFlashMessageQueue(
                    LanguageUtility::getLL('send_recipients') . ' ' . htmlspecialchars($addressList),
                    LanguageUtility::getLL('testMailSent')
                );
            }
        }
    }

    /**
     * Send personalized test mails
     *
     * @param array $row mail DB record
     */
    protected function sendPersonalizedTestMails(array $row): void
    {
        // Preparing mailer
        $this->mailerService->start();
        $this->mailerService->prepare($row);
        $sentFlag = false;

        // step 4, sending test personalized test emails
        // setting Testmail flag
        $this->mailerService->setTestMail($this->isTestMail);
        // todo create personalized test mails
        /*
        if ($this->tt_address_uid) {
            // personalized to tt_address
            $res = $this->ttAddressRepository->findByUidAndPermissionClause($this->tt_address_uid,
                $this->backendUserPermissions);

            if (!empty($res)) {
                foreach ($res as $recipRow) {
                    $recipRow = MailerUtility::normalizeAddress($recipRow);
                    $recipRow['sys_dmail_categories_list'] = MailerUtility::getListOfRecipientCategories('tt_address', $recipRow['uid']);
                    $this->mailerService->sendAdvanced($recipRow, 't');
                    $sentFlag = true;

                    $message = MailerUtility::getFlashMessage(
                        sprintf(MailerUtility::getLL('send_was_sent_to_name'), $recipRow['name'] . ' <' . $recipRow['email'] . '>'),
                        MailerUtility::getLL('send_sending'),
                        AbstractMessage::OK
                    );
                    $this->messageQueue->addMessage($message);
                }
            } else {
                $message = MailerUtility::getFlashMessage(
                    'Error: No valid recipient found to send test mail to. #1579209279',
                    MailerUtility::getLL('send_sending'),
                    AbstractMessage::ERROR
                );
                $this->messageQueue->addMessage($message);
            }
        } else {
            if (is_array(GeneralUtility::_GP('sys_dmail_group_uid'))) {
                // personalized to group
                $idLists = $this->recipientService->getRecipientIdsOfMailGroups(GeneralUtility::_GP('sys_dmail_group_uid'));

                $sendFlag = 0;
                $sendFlag += $this->sendTestMailToTable($idLists, 'tt_address');
                $sendFlag += $this->sendTestMailToTable($idLists, 'fe_users');
                $sendFlag += $this->sendTestMailToTable($idLists, 'PLAINLIST');
                if ($this->userTable) {
                    $sendFlag += $this->sendTestMailToTable($idLists, $this->userTable);
                }
                $message = MailerUtility::getFlashMessage(
                    sprintf(MailerUtility::getLL('send_was_sent_to_number'), $sendFlag),
                    MailerUtility::getLL('send_sending'),
                    AbstractMessage::OK
                );
                $this->messageQueue->addMessage($message);
            }
        }
        */
        // Setting flags and update the record:
        if ($sentFlag && $this->action->equals(Action::WIZARD_STEP_FINAL)) {
            $this->sysDmailRepository->update($this->mailUid, ['issent' => 1]);
        }
    }

    /**
     * Sending the mail.
     * if it's a test mail, then will be sent directly.
     * if mass-send mail, only update the DB record. the command controller will send it.
     *
     * @param array $row mail DB record
     *
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function schedulePersonalizedMails(array $row): void
    {
        // Preparing mailer
        $this->mailerService->start();
        $this->mailerService->prepare($row);
        $sentFlag = false;

        if ($this->scheduleSendAll && $this->mailUid) {
            // Update the record:
            $queryInfo['id_lists'] = $this->recipientService->getRecipientIdsOfMailGroups($this->mailGroupUids);

            // todo: cast recipient groups to integer
            $updateFields = [
                'recipientGroups' => implode(',', $this->mailGroupUids),
                'scheduled' => $this->distributionTimeStamp,
                'query_info' => serialize($queryInfo),
            ];

            if ($this->isTestMail) {
                $updateFields['subject'] = ($this->pageTSConfiguration['testmail'] ?? '') . ' ' . $row['subject'];
            }

            // create a draft version of the record
            if ($this->saveDraft) {
                if ($row['type'] === MailType::INTERNAL) {
                    $updateFields['type'] = MailType::DRAFT_INTERNAL;
                } else {
                    $updateFields['type'] = MailType::DRAFT_EXTERNAL;
                }
                $updateFields['scheduled'] = 0;
                ViewUtility::addOkToFlashMessageQueue(
                    sprintf(LanguageUtility::getLL('send_draft_scheduler'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
                    LanguageUtility::getLL('send_draft_saved'), true
                );
            } else {
                ViewUtility::addOkToFlashMessageQueue(
                    sprintf(LanguageUtility::getLL('send_was_scheduled_for'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
                    LanguageUtility::getLL('send_was_scheduled'), true
                );
            }
            $this->sysDmailRepository->update($this->mailUid, $updateFields);
            $sentFlag = true;
        }

        // Setting flags and update the record:
        if ($sentFlag) {
            $this->sysDmailRepository->update($this->mailUid, ['issent' => 1]);
        }
    }
}
