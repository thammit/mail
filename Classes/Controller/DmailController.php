<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtAddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;

class DmailController extends AbstractController
{
    protected string $cshTable;
    protected string $error = '';
    protected int $currentStep = 1;
    protected int $uid = 0;
    protected bool $backButtonPressed = false;
    protected string $currentCMD = '';
    protected bool $fetchAtOnce = false;
    protected array $quickMail = [];
    protected int $createMailFromPageUid = 0;
    protected int $createMailForLanguageUid = 0;
    protected string $subjectForExternalMail = '';
    protected string $externalMailHtmlUri = '';
    protected string $externalMailPlainUri = '';
    protected array $mailGroupUids = [];
    protected bool $sendTestMail = false;
    protected string $sendTestMailAddress = '';
    // protected int $tt_address_uid = 0;
    protected string $requestUri = '';

    /**
     * The route of the module
     *
     * @var string
     */
    protected string $route = 'MailNavFrame_Mail';
    protected string $moduleName = 'MailNavFrame_Mail';

    /**
     * Init module
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    protected function init(ServerRequestInterface $request): void
    {
        parent::init($request);

        $this->cshTable = '_MOD_' . $this->moduleName;

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestUri = $normalizedParams->getRequestUri();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);

        if ($parsedBody['update_cats'] ?? $queryParams['update_cats'] ?? false) {
            $this->cmd = Constants::WIZARD_STEP_CATEGORIES;
        }

        $this->sendTestMail = (bool)($parsedBody['sendTestMail']['send'] ?? $queryParams['sendTestMail']['send'] ?? false);
        $this->sendTestMailAddress = (string)($parsedBody['sendTestMail']['address'] ?? $queryParams['sendTestMail']['address'] ?? '');
        if ($this->sendTestMail) {
            $this->cmd = Constants::WIZARD_STEP_SEND_TEST2;
        }

        $this->backButtonPressed = (bool)($parsedBody['back'] ?? $queryParams['back'] ?? false);

        $this->currentCMD = (string)($parsedBody['currentCMD'] ?? $queryParams['currentCMD'] ?? '');
        // Create mail and fetch the data
        $this->fetchAtOnce = (bool)($parsedBody['fetchAtOnce'] ?? $queryParams['fetchAtOnce'] ?? false);

        $this->createMailFromPageUid = (int)($parsedBody['createMailFromPageUid'] ?? $queryParams['createMailFromPageUid'] ?? 0);
        $this->createMailForLanguageUid = (int)($parsedBody['createMailForLanguageUid'] ?? $queryParams['createMailForLanguageUid'] ?? 0);

        $this->subjectForExternalMail = (string)($parsedBody['subjectForExternalMail'] ?? $queryParams['subjectForExternalMail'] ?? '');
        $this->externalMailHtmlUri = (string)($parsedBody['externalMailHtmlUri'] ?? $queryParams['externalMailHtmlUri'] ?? '');
        $this->externalMailPlainUri = (string)($parsedBody['externalMailPlainUri'] ?? $queryParams['externalMailPlainUri'] ?? '');

        $this->quickMail = (array)($parsedBody['quickmail'] ?? $queryParams['quickmail'] ?? []);

        $this->mailGroupUids = $parsedBody['mailGroupUid'] ?? $queryParams['mailGroupUid'] ?? [];
        // $this->tt_address_uid = (int)($parsedBody['tt_address_uid'] ?? $queryParams['tt_address_uid'] ?? 0);
        $this->view->assign('settings', [
            'route' => $this->route,
            'mailSysFolderUid' => $this->id,
            'steps' => [
                'overview' => Constants::WIZARD_STEP_OVERVIEW,
                'settings' => Constants::WIZARD_STEP_SETTINGS,
                'categories' => Constants::WIZARD_STEP_CATEGORIES,
                'sendTest' => Constants::WIZARD_STEP_SEND_TEST,
                'sendTest2' => Constants::WIZARD_STEP_SEND_TEST2,
                'final' => Constants::WIZARD_STEP_FINAL,
                'send' => Constants::WIZARD_STEP_SEND,
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
        $this->view->setTemplate('Wizard');

        if (($this->id && $this->access) || (BackendUserUtility::isAdmin() && !$this->id)) {
            // get module name of the selected page in the page tree
            if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $this->view->assignMultiple($this->getModuleData());
                } else {
                    if ($this->id != 0) {
                        $this->messageQueue->addMessage(ViewUtility::getFlashMessage(LanguageUtility::getLL('dmail_noRegular'),
                            LanguageUtility::getLL('dmail_newsletters'), AbstractMessage::WARNING));
                    }
                }
            } else {
                // Todo search for dmail modules the tree up and if found open the wizard settings step of the selected page
                $this->messageQueue->addMessage(ViewUtility::getFlashMessage('Todo search for dmail modules the tree up and if found open the wizard settings step of the selected page',
                    'Todo', AbstractMessage::NOTICE));
                $this->messageQueue->addMessage(ViewUtility::getFlashMessage(LanguageUtility::getLL('select_folder'),
                    LanguageUtility::getLL('header_directmail'),
                    AbstractMessage::WARNING));
            }
        } else {
            // If no access or if ID == zero
            $this->view->setTemplate('NoAccess');
            $this->messageQueue->addMessage(ViewUtility::getFlashMessage('If no access or if ID == zero', 'No Access', AbstractMessage::WARNING));
        }

        /**
         * Render template and return html content
         */
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
        $sysDmailRepository = GeneralUtility::makeInstance(SysDmailRepository::class);

        if ($this->cmd == 'delete') {
            $sysDmailRepository->delete($this->uid);
        }

        $isExternalDirectMailRecord = false;
        $mailData = [];
        if ($this->sys_dmail_uid) {
            // get the data of the currently selected mail
            $mailData = $sysDmailRepository->findByUid($this->sys_dmail_uid);
            $isExternalDirectMailRecord = is_array($mailData) && (int)$mailData['type'] === Constants::MAIL_TYPE_EXTERNAL;
        }

        $userTSConfig = TypoScriptUtility::getUserTSConfig();
        $hideCategoryStep = $isExternalDirectMailRecord || (isset($userTSConfig['tx_directmail.']['hideSteps']) && $userTSConfig['tx_directmail.']['hideSteps'] === 'cat');

        if ($this->backButtonPressed) {
            // CMD move 1 step back
            switch ($this->currentCMD) {
                case Constants::WIZARD_STEP_SETTINGS:
                    $this->cmd = Constants::WIZARD_STEP_OVERVIEW;
                    break;
                case Constants::WIZARD_STEP_CATEGORIES:
                    $this->cmd = Constants::WIZARD_STEP_SETTINGS;
                    break;
                case Constants::WIZARD_STEP_SEND_TEST:
                    // Same as send_mail_test
                case Constants::WIZARD_STEP_SEND_TEST2:
                    if ($this->cmd === Constants::WIZARD_STEP_SEND && $hideCategoryStep) {
                        $this->cmd = Constants::WIZARD_STEP_SETTINGS;
                    } else {
                        $this->cmd = Constants::WIZARD_STEP_CATEGORIES;
                    }
                    break;
                case Constants::WIZARD_STEP_FINAL:
                    // The same as send_mass
                case Constants::WIZARD_STEP_SEND:
                    $this->cmd = Constants::WIZARD_STEP_SEND_TEST;
                    break;
                default:
                    // Do nothing
            }
        }

        $nextCmd = '';
        if ($hideCategoryStep) {
            $totalSteps = 4;
            if ($this->cmd == Constants::WIZARD_STEP_SETTINGS) {
                $nextCmd = Constants::WIZARD_STEP_SEND_TEST;
            }
        } else {
            $totalSteps = 5;
            if ($this->cmd == Constants::WIZARD_STEP_SETTINGS) {
                $nextCmd = Constants::WIZARD_STEP_CATEGORIES;
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

        switch ($this->cmd) {
            case Constants::WIZARD_STEP_SETTINGS:
                // step 2: create the Direct Mail record, or use existing
                $this->currentStep = 2;
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['info'] = [
                    'currentStep' => $this->currentStep,
                ];

                // greyed out next-button if fetching is not successful (on error)
                $fetchError = true;

                $quickmail = $this->quickMail;
                $quickmail['send'] = $quickmail['send'] ?? false;

                // internal page
                if ($this->createMailFromPageUid && !$quickmail['send']) {
                    $newUid = $this->createMailRecordFromInternalPage($this->createMailFromPageUid, $this->pageTSConfiguration,
                        $this->createMailForLanguageUid);
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $mailData = $sysDmailRepository->findByUid($newUid);
                        // fetch the data
                        if ($this->fetchAtOnce) {
                            $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                        }

                        $moduleData['info']['internal']['cmd'] = $nextCmd ?: Constants::WIZARD_STEP_CATEGORIES;
                    } else {
                        // TODO: Error message - Error while adding the DB set
                    }
                }
                // external URL
                else {
                    if ($this->subjectForExternalMail != '' && !$quickmail['send']) {
                        $newUid = $this->createMailRecordFromExternalUrls(
                            $this->subjectForExternalMail,
                            $this->externalMailHtmlUri,
                            $this->externalMailPlainUri,
                            $this->pageTSConfiguration
                        );
                        if (is_numeric($newUid)) {
                            $this->sys_dmail_uid = $newUid;
                            // Read new record (necessary because TCEmain sets default field values)
                            $mailData = $sysDmailRepository->findByUid($newUid);
                            // fetch the data
                            if ($this->fetchAtOnce) {
                                $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                            }

                            $moduleData['info']['external']['cmd'] = Constants::WIZARD_STEP_SEND_TEST;
                        } else {
                            // TODO: Error message - Error while adding the DB set
                            $this->error = 'no_valid_url';
                            $this->messageQueue->addMessage(ViewUtility::getFlashMessage(LanguageUtility::getLL('dmail_external_html_uri_is_invalid') . ' Requested URL: ' . $this->externalMailHtmlUri,
                                LanguageUtility::getLL('dmail_error'), AbstractMessage::ERROR));
                        }
                    } // Quickmail
                    else {
                        if ($quickmail['send']) {
                            $temp = $this->createQuickMail($quickmail);
                            if (!$temp['errorTitle']) {
                                $fetchError = false;
                            }
                            if ($temp['errorTitle']) {
                                $this->messageQueue->addMessage(ViewUtility::getFlashMessage($temp['errorText'], $temp['errorTitle'],
                                    AbstractMessage::ERROR));
                            }
                            if ($temp['warningTitle']) {
                                $this->messageQueue->addMessage(ViewUtility::getFlashMessage($temp['warningText'], $temp['warningTitle'],
                                    AbstractMessage::WARNING));
                            }

                            // Todo Check if we do not need the newly created quick mail here
                            $mailData = $sysDmailRepository->findByUid($this->sys_dmail_uid);

                            $moduleData['info']['quickmail']['cmd'] = Constants::WIZARD_STEP_SEND_TEST;
                            $moduleData['info']['quickmail']['senderName'] = htmlspecialchars($quickmail['senderName'] ?? '');
                            $moduleData['info']['quickmail']['senderEmail'] = htmlspecialchars($quickmail['senderEmail'] ?? '');
                            $moduleData['info']['quickmail']['subject'] = htmlspecialchars($quickmail['subject'] ?? '');
                            $moduleData['info']['quickmail']['message'] = htmlspecialchars($quickmail['message'] ?? '');
                            $moduleData['info']['quickmail']['breakLines'] = ($quickmail['breakLines'] ?? false) ? (int)$quickmail['breakLines'] : 0;
                        } // existing dmail
                        else {
                            if ($mailData) {
                                if ($mailData['type'] == '1' && (empty($mailData['HTMLParams']) || empty($mailData['plainParams']))) {
                                    // it's a quickmail
                                    $fetchError = false;

                                    $moduleData['info']['dmail']['cmd'] = Constants::WIZARD_STEP_SEND_TEST;

                                    // add attachment here, since attachment added in 2nd step
                                    $unserializedMailContent = unserialize(base64_decode($mailData['mailContent']));
                                    $temp = $this->compileQuickMail($mailData, $unserializedMailContent['plain']['content'] ?? '', false);
                                    if ($temp['errorTitle']) {
                                        $this->messageQueue->addMessage(ViewUtility::getFlashMessage($temp['errorText'], $temp['errorTitle'],
                                            AbstractMessage::ERROR));
                                    }
                                    if ($temp['warningTitle']) {
                                        $this->messageQueue->addMessage(ViewUtility::getFlashMessage($temp['warningText'], $temp['warningTitle'],
                                            AbstractMessage::WARNING));
                                    }
                                } else {
                                    if ($this->fetchAtOnce) {
                                        $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                                    }

                                    $moduleData['info']['dmail']['cmd'] = ($mailData['type'] == 0) ? $nextCmd : Constants::WIZARD_STEP_SEND_TEST;
                                }
                            }
                        }
                    }
                }

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;
                $moduleData['navigation']['nextError'] = $fetchError;

                if (!$fetchError && $this->fetchAtOnce) {
                    $this->messageQueue->addMessage(ViewUtility::getFlashMessage(
                        '',
                        LanguageUtility::getLL('dmail_wiz2_fetch_success'),
                        AbstractMessage::OK
                    ));
                }
                $moduleData['info']['table'] = is_array($mailData) ? $this->getGroupedMailSettings($mailData) : '';
                $moduleData['info']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $moduleData['info']['pages_uid'] = $mailData['page'] ?: '';
                $moduleData['info']['currentCMD'] = $this->cmd;
                break;

            case Constants::WIZARD_STEP_CATEGORIES:
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
                // $moduleData['cats']['catsForm'] = $temp['theOutput'];

                $moduleData['cats']['cmd'] = Constants::WIZARD_STEP_SEND_TEST;
                $moduleData['cats']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $moduleData['cats']['pages_uid'] = $this->pages_uid;
                $moduleData['cats']['currentCMD'] = $this->cmd;
                break;

            case Constants::WIZARD_STEP_SEND_TEST:
                // Same as send_mail_test
            case Constants::WIZARD_STEP_SEND_TEST2:
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['test'] = [
                    'currentStep' => $this->currentStep,
                ];

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;

                if ($this->cmd === Constants::WIZARD_STEP_SEND_TEST2) {
                    $this->sendMail($mailData);
                }
                $moduleData['test']['testFormData'] = $this->getTestMailConfig();
                $moduleData['test']['cmd'] = Constants::WIZARD_STEP_SEND;
                $moduleData['test']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $moduleData['test']['pages_uid'] = $this->pages_uid;
                $moduleData['test']['currentCMD'] = $this->cmd;
                break;

            case Constants::WIZARD_STEP_FINAL:
                // same as send_mass
            case Constants::WIZARD_STEP_SEND:
                $this->currentStep = 5 - (5 - $totalSteps);
                $moduleData['navigation']['currentStep'] = $this->currentStep;
                $moduleData['final'] = ['currentStep' => $this->currentStep];

                if ($this->cmd === Constants::WIZARD_STEP_SEND) {
                    $moduleData['navigation']['back'] = true;
                }

                if ($this->cmd === Constants::WIZARD_STEP_FINAL) {
                    if (is_array($this->mailGroupUids) && count($this->mailGroupUids)) {
                        $this->sendMail($mailData);
                        break;
                    } else {
                        $this->messageQueue->addMessage(ViewUtility::getFlashMessage(
                            LanguageUtility::getLL('mod.no_recipients'),
                            '',
                            AbstractMessage::WARNING
                        ));
                    }
                }
                // send mass, show calendar
                $moduleData['final']['finalForm'] = $this->getFinalData($mailData);
                $moduleData['final']['cmd'] = Constants::WIZARD_STEP_FINAL;
                $moduleData['final']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $moduleData['final']['pages_uid'] = $this->pages_uid;
                $moduleData['final']['currentCMD'] = $this->cmd;
                break;

            case Constants::WIZARD_STEP_OVERVIEW:
            default:
                // choose source newsletter
                $this->currentStep = 1;

                $showTabs = [Constants::PANEL_INTERNAL, Constants::PANEL_EXTERNAL, Constants::PANEL_QUICK_MAIL, Constants::PANEL_OPEN_STORED];
                if (isset($userTSConfig['tx_directmail.']['hideTabs'])) {
                    $hideTabs = GeneralUtility::trimExplode(',', $userTSConfig['tx_directmail.']['hideTabs']);
                    foreach ($hideTabs as $hideTab) {
                        $showTabs = ArrayUtility::removeArrayEntryByValue($showTabs, $hideTab);
                    }
                }
                if (!isset($userTSConfig['tx_directmail.']['defaultTab'])) {
                    $userTSConfig['tx_directmail.']['defaultTab'] = Constants::PANEL_OPEN_STORED;
                }

                foreach ($showTabs as $showTab) {
                    $open = $userTSConfig['tx_directmail.']['defaultTab'] == $showTab;
                    switch ($showTab) {
                        case Constants::PANEL_INTERNAL:
                            $temp['data'] = GeneralUtility::makeInstance(PagesRepository::class)->findMailPages($this->id, $this->backendUserPermissions);
                            $temp['open'] = $open;
                            $moduleData['default']['internal'] = $temp;
                            break;
                        case Constants::PANEL_EXTERNAL:
                            $temp['open'] = $open;
                            $moduleData['default']['external'] = $temp;
                            break;
                        case Constants::PANEL_QUICK_MAIL:
                            $temp = $this->getQuickMailConfig();
                            $temp['open'] = $open;
                            $moduleData['default']['quickMail'] = $temp;
                            break;
                        case Constants::PANEL_OPEN_STORED:
                            $temp['data'] = GeneralUtility::makeInstance(SysDmailRepository::class)->findOpenMailsByPageId($this->id);
                            $temp['open'] = $open;
                            $moduleData['default']['openStored'] = $temp;
                            break;
                        default:
                    }
                }
        }

        return $moduleData;
    }

    /**
     * Get external page config
     *
     * @return array config for form for inputing the external page information
     */
    protected function getExternalPageConfig(): array
    {
        return [
            'title' => 'dmail_dovsk_crFromUrl',
            'no_valid_url' => $this->error == 'no_valid_url',
        ];
    }

    /**
     * Get quick mail config
     *
     * @return array config for form for the quick mail
     */
    protected function getQuickMailConfig(): array
    {
        return [
            'id' => $this->id,
            'senderName' => htmlspecialchars($this->quickMail['senderName'] ?? BackendUserUtility::getBackendUser()->user['realName']),
            'senderMail' => htmlspecialchars($this->quickMail['senderEmail'] ?? BackendUserUtility::getBackendUser()->user['email']),
            'subject' => htmlspecialchars($this->quickMail['subject'] ?? ''),
            'message' => htmlspecialchars($this->quickMail['message'] ?? ''),
            'breakLines' => (bool)($this->quickMail['breakLines'] ?? false),
        ];
    }

    /**
     * Create a quick mail record.
     *
     * @param array $indata Quickmail data (quickmail content, etc.)
     * @return array|string error or warning message produced during the process
     * @throws DBALException
     * @throws SiteNotFoundException
     */
    protected function createQuickMail(array $indata): array|string
    {
        $theOutput = [];
        // Set default values:
        $dmail = [];
        $dmail['sys_dmail']['NEW'] = [
            'from_email' => $indata['senderEmail'],
            'from_name' => $indata['senderName'],
            'replyto_email' => $this->pageTSConfiguration['replyto_email'] ?? '',
            'replyto_name' => $this->pageTSConfiguration['replyto_name'] ?? '',
            'return_path' => $this->pageTSConfiguration['return_path'] ?? '',
            'priority' => (int)($this->pageTSConfiguration['priority'] ?? 3),
            'use_rdct' => (int)($this->pageTSConfiguration['use_rdct'] ?? 0),
            'long_link_mode' => (int)($this->pageTSConfiguration['long_link_mode'] ?? 0),
            'organisation' => $this->pageTSConfiguration['organisation'] ?? '',
            'authcode_fieldList' => $this->pageTSConfiguration['authcode_fieldList'] ?? '',
            'plainParams' => '',
        ];

        // always plaintext
        $dmail['sys_dmail']['NEW']['sendOptions'] = 1;
        $dmail['sys_dmail']['NEW']['long_link_rdct_url'] = $this->getUrlBase((int)$this->pageTSConfiguration['pid']);
        $dmail['sys_dmail']['NEW']['subject'] = $indata['subject'];
        $dmail['sys_dmail']['NEW']['type'] = 1;
        $dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
        $dmail['sys_dmail']['NEW']['charset'] = $this->pageTSConfiguration['quick_mail_charset'] ?? 'utf-8';

        // If params set, set default values:
        if (isset($this->pageTSConfiguration['includeMedia'])) {
            $dmail['sys_dmail']['NEW']['includeMedia'] = $this->pageTSConfiguration['includeMedia'];
        }
        if (isset($this->pageTSConfiguration['flowedFormat'])) {
            $dmail['sys_dmail']['NEW']['flowedFormat'] = $this->pageTSConfiguration['flowedFormat'];
        }
        if (isset($this->pageTSConfiguration['direct_mail_encoding'])) {
            $dmail['sys_dmail']['NEW']['encoding'] = $this->pageTSConfiguration['direct_mail_encoding'];
        }

        if ($dmail['sys_dmail']['NEW']['pid'] && $dmail['sys_dmail']['NEW']['sendOptions']) {
            $dataHandler = $this->getDataHandler();
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($dmail, []);
            $dataHandler->process_datamap();
            $this->sys_dmail_uid = $dataHandler->substNEWwithIDs['NEW'];

            $row = BackendUtility::getRecord('sys_dmail', intval($this->sys_dmail_uid));
            // link in the mail
            $message = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_-->' . $indata['message'] . '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';
            if ($this->pageTSConfiguration['use_rdct'] ?? false) {
                $message = MailerUtility::shortUrlsInPlainText(
                    $message,
                    $this->pageTSConfiguration['long_link_mode'] ? 0 : 76,
                    $this->getUrlBase((int)$this->pageTSConfiguration['pid'])
                );
            }
            if ($indata['breakLines'] ?? false) {
                $message = wordwrap($message, 76);
            }
            // fetch functions
            $theOutput = $this->compileQuickMail($row, $message);
            // end fetch function
        } else {
            if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
                $this->error = 'no_valid_url';
            }
        }

        return $theOutput;
    }

    /**
     * Compiling the quickmail content and save to DB
     *
     * @param array $row The sys_dmail record
     * @param string $message Body of the mail
     *
     * @return array
     * @throws DBALException
     * @TODO: remove htmlmail, compiling mail
     */
    protected function compileQuickMail(array $row, string $message): array
    {
        $erg = ['errorTitle' => '', 'errorText' => '', 'warningTitle' => '', 'warningText' => ''];

        // Compile the mail
        $this->mailerService->start();
        $this->mailerService->setCharset($row['charset']);
        $this->mailerService->addPlainContent($message);

        if (!$message || !$this->mailerService->getPlainContent()) {
            $erg['errorTitle'] = LanguageUtility::getLL('dmail_error');
            $erg['errorText'] = LanguageUtility::getLL('dmail_no_plain_content');
        } else {
            if (!str_contains(base64_decode($this->mailerService->getPlainContent()), '<!--' . Constants::CONTENT_SECTION_BOUNDARY)) {
                $erg['warningTitle'] = LanguageUtility::getLL('dmail_warning');
                $erg['warningText'] = LanguageUtility::getLL('dmail_no_plain_boundaries');
            }
        }

        // add attachment is removed. since it will be added during sending

        if (!$erg['errorTitle']) {
            // Update the record:
            $this->mailerService->setMailPart('messageid', $this->mailerService->getMessageId());
            $mailContent = base64_encode(serialize($this->mailerService->getMailParts()));

            GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmail(
                $this->sys_dmail_uid,
                $this->mailerService->getCharset(), $mailContent
            );
        }

        return $erg;
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
            'cmd' => Constants::WIZARD_STEP_SEND_TEST2,
            'sys_dmail_uid' => $this->sys_dmail_uid,
            'dmail_test_email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
            'test_tt_address' => '',
            'test_dmail_group_table' => [],
        ];

        if ($this->pageTSConfiguration['test_tt_address_uids'] ?? false) {
            $testTtAddressUids = implode(',', GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_tt_address_uids']));
            $data['ttAddress'] = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForTestmail($testTtAddressUids, $this->backendUserPermissions);
//
//            $ids = [];
//
//            foreach ($rows as $row) {
//                $ids[] = $row['uid'];
//            }
//            $data['ttAddress'] = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($ids, 'tt_address');
//            $data['ttAddress'] = $this->getRecordListHtmlTable($rows, 'tt_address', 1, 1);
        }

        if ($this->pageTSConfiguration['test_dmail_group_uids'] ?? false) {
            $testMailGroupUids = implode(',', GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_dmail_group_uids']));
            $data['mailGroups'] = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForTestmail($testMailGroupUids, $this->backendUserPermissions);

//            foreach ($rows as $row) {
//                $moduleUrl = $this->getWizardStepUri(Constants::WIZARD_STEP_SEND_TEST2, [
//                    'sys_dmail_uid' => $this->sys_dmail_uid,
//                    'sys_dmail_group_uid[]' => $row['uid'],
//                ]);
//
//                // Members:
//                $result = $this->recipientService->getRecipientIdsOfMailGroups([$row['uid']]);
//
//                $data['test_dmail_group_table'][] = [
//                    'moduleUrl' => $moduleUrl,
//                    'iconFactory' => $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL),
//                    'title' => htmlspecialchars($row['title']),
//                    'tds' => $this->displayMailGroup_test($result),
//                ];
//            }
        }

        return $data;
    }

    /**
     * Display the test mail group, which configured in the configuration module
     *
     * @param array $idLists Lists of recipient uids
     *
     * @return string List of the recipient (in HTML)
     * @throws DBALException
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function displayMailGroup_test(array $idLists): string
    {
        $out = '';
        if (is_array($idLists['tt_address'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['tt_address'], 'tt_address');
            $out .= $this->getRecordListHtmlTable($rows, 'tt_address');
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
            $out .= $this->getRecordListHtmlTable($rows, 'fe_users');
        }
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $out .= $this->getRecordListHtmlTable($idLists['PLAINLIST'], 'default');
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
            $out .= $this->getRecordListHtmlTable($rows, $this->userTable);
        }

        return $out;
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
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function sendMail(array $row): void
    {
        // Preparing mailer
        $this->mailerService->start();
        $this->mailerService->prepare($row);
        $sentFlag = false;

        // send out non-personalized emails
        if ($this->sendTestMail) {
            // step 4, sending simple test emails
            // setting Testmail flag
            $this->mailerService->setTestMail((bool)($this->pageTSConfiguration['testmail'] ?? false));

            // Fixing addresses:
            $addresses = preg_split('|[' . chr(10) . ',;]|', $this->sendTestMailAddress);

            foreach ($addresses as $key => $val) {
                $addresses[$key] = trim($val);
                if (!GeneralUtility::validEmail($addresses[$key])) {
                    unset($addresses[$key]);
                }
            }
            $hash = array_flip($addresses);
            $addresses = array_keys($hash);
            $addressList = implode(',', $addresses);

            if ($addressList) {
                // Sending the same mail to lots of recipients
                $this->mailerService->sendSimple($addressList);
                $sentFlag = true;
                $message = ViewUtility::getFlashMessage(
                    LanguageUtility::getLL('send_was_sent') . ' ' .
                    LanguageUtility::getLL('send_recipients') . ' ' . htmlspecialchars($addressList),
                    LanguageUtility::getLL('send_sending'),
                    AbstractMessage::OK
                );
                $this->messageQueue->addMessage($message);

                //$this->noView = 1;
            }
        } else {
            if ($this->cmd === Constants::WIZARD_STEP_SEND_TEST2) {
                // step 4, sending test personalized test emails
                // setting Testmail flag
                $this->mailerService->setTestMail((bool)($this->pageTSConfiguration['testmail'] ?? false));
                /*
                if ($this->tt_address_uid) {
                    // personalized to tt_address
                    $res = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForSendMailTest($this->tt_address_uid,
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
            } else {
                // step 5, sending personalized emails to the mailqueue

                // prepare the email for sending with the mailqueue
                $recipientGroups = GeneralUtility::_GP('mailgroup_uid');
                if (GeneralUtility::_GP('mailingMode_mailGroup') && $this->sys_dmail_uid && is_array($recipientGroups)) {
                    // Update the record:
                    $queryInfo['id_lists'] = $this->recipientService->getRecipientIdsOfMailGroups($recipientGroups);

                    $distributionTime = strtotime(GeneralUtility::_GP('send_mail_datetime'));
                    if ($distributionTime < time()) {
                        $distributionTime = time();
                    }

                    $updateFields = [
                        'recipientGroups' => implode(',', $recipientGroups),
                        'scheduled' => $distributionTime,
                        'query_info' => serialize($queryInfo),
                    ];

                    if (GeneralUtility::_GP('testmail')) {
                        $updateFields['subject'] = ($this->pageTSConfiguration['testmail'] ?? '') . ' ' . $row['subject'];
                    }

                    // create a draft version of the record
                    if (GeneralUtility::_GP('savedraft')) {
                        if ($row['type'] == 0) {
                            $updateFields['type'] = 2;
                        } else {
                            $updateFields['type'] = 3;
                        }

                        $updateFields['scheduled'] = 0;
                        $content = LanguageUtility::getLL('send_draft_scheduler');
                        $sectionTitle = LanguageUtility::getLL('send_draft_saved');
                    } else {
                        $content = LanguageUtility::getLL('send_was_scheduled_for') . ' ' . BackendUtility::datetime($distributionTime);
                        $sectionTitle = LanguageUtility::getLL('send_was_scheduled');
                    }
                    $sentFlag = true;
                    $connection = $this->getConnection('sys_dmail');
                    $connection->update(
                        'sys_dmail', // table
                        $updateFields,
                        ['uid' => $this->sys_dmail_uid] // where
                    );

                    $message = ViewUtility::getFlashMessage(
                        $sectionTitle . ' ' . $content,
                        LanguageUtility::getLL('dmail_wiz5_sendmass'),
                        AbstractMessage::OK
                    );
                    $this->messageQueue->addMessage($message);
                }
            }
        }

        // Setting flags and update the record:
        if ($sentFlag && $this->cmd === Constants::WIZARD_STEP_FINAL) {

            $connection = $this->getConnection('sys_dmail');
            $connection->update(
                'sys_dmail', // table
                ['issent' => 1],
                ['uid' => $this->sys_dmail_uid] // where
            );
        }
    }

    /**
     * Send mail to recipient based on table.
     *
     * @param array $idLists List of recipient ID
     * @param string $table Table name
     *
     * @return int Total of sent mail
     * @throws DBALException
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws \TYPO3\CMS\Core\Exception
     * @todo: remove htmlmail. sending mails to table
     */
    protected function sendTestMailToTable(array $idLists, string $table): int
    {
        $sentFlag = 0;
        if (isset($idLists[$table]) && is_array($idLists[$table])) {
            if ($table != 'PLAINLIST') {
                $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$table], $table, ['*']);
            } else {
                $rows = $idLists['PLAINLIST'];
            }
            foreach ($rows as $rec) {
                $recipRow = RecipientUtility::normalizeAddress($rec);
                $recipRow['sys_dmail_categories_list'] = RecipientUtility::getListOfRecipientCategories($table, $recipRow['uid']);
                $kc = substr($table, 0, 1);
                $returnCode = $this->mailerService->sendAdvanced($recipRow, $kc == 'p' ? 'P' : $kc);
                if ($returnCode) {
                    $sentFlag++;
                }
            }
        }
        return $sentFlag;
    }

    /**
     * Show the recipient info and a link to edit it
     *
     * @param array $listArr List of recipients ID
     * @param string $table Table name
     * @param bool|int $editLinkFlag If set, edit link is showed
     * @param bool|int $testMailLink If set, send mail link is showed
     *
     * @return string HTML, the table showing the recipient's info
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function getRecordListHtmlTable(array $listArr, string $table, bool|int $editLinkFlag = 1, bool|int $testMailLink = 0): string
    {
        $lines = [];
        $out = '';
        $iconActionsOpen = $this->getIconActionsOpen();
        $count = count($listArr);
        foreach ($listArr as $row) {
            $tableIcon = '';
            $editLink = '';
            $testLink = '';

            if ($row['uid']) {
                $tableIcon = '<td>' . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL) . '</td>';
                if ($editLinkFlag) {
                    $requestUri = $this->requestUri . '&cmd=send_test&sys_dmail_uid=' . $this->sys_dmail_uid . '&pages_uid=' . $this->pages_uid;

                    $params = [
                        'edit' => [
                            $table => [
                                $row['uid'] => 'edit',
                            ],
                        ],
                        'returnUrl' => $requestUri,
                    ];

                    $editOnClick = ViewUtility::getEditOnClickLink($params);
                    $editLink = '<td><a href="#" onClick="' . $editOnClick . '" title="' . LanguageUtility::getLL('dmail_edit') . '">' .
                        $iconActionsOpen .
                        '</a></td>';
                }

                if ($testMailLink) {
                    $moduleUrl = $this->getWizardStepUri(Constants::WIZARD_STEP_SEND_TEST2, [
                        'sys_dmail_uid' => $this->sys_dmail_uid,
                        'tt_address_uid' => $row['uid'],
                    ]);
                    $testLink = '<a href="' . $moduleUrl . '">' . htmlspecialchars($row['email']) . '</a>';
                } else {
                    $testLink = htmlspecialchars($row['email']);
                }
            }

            $lines[] = '<tr class="db_list_normal">
				' . $tableIcon . '
				' . $editLink . '
				<td nowrap> ' . $testLink . ' </td>
				<td nowrap> ' . htmlspecialchars($row['name']) . ' </td>
				</tr>';
        }
        if (count($lines)) {
            $out = '<p>' . LanguageUtility::getLL('dmail_number_records') . ' <strong>' . $count . '</strong></p><br />';
            $out .= '<table class="table table-striped table-hover">' . implode(chr(10), $lines) . '</table>';
        }
        return $out;
    }

    /**
     * Get data for the final step
     * Show recipient list and calendar library
     *
     * @param array $direct_mail_row
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getFinalData(array $direct_mail_row): array
    {
        /**
         * Hook for cmd_finalmail
         * insert a link to open extended importer
         */
        // Todo: Change to PSR-14 Event Dispatcher
        $hookContents = '';
        $hookSelectDisabled = false;
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

        // Mail groups
        $groups = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForFinalMail(
            $this->id,
            (int)$direct_mail_row['sys_language_uid'],
            trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
        );

        $mailGroups = RecipientUtility::finalSendingGroups($this->id, $this->sys_dmail_uid, $groups, $this->userTable, $this->backendUserPermissions);

        // todo: delete next block after upper method is working

        $opt = [];
        $lastGroup = null;
        if ($groups) {
            foreach ($groups as $group) {
                $count = 0;
                $idLists = $this->recipientService->getRecipientIdsOfMailGroups([$group['uid']]);
                if (is_array($idLists['tt_address'] ?? false)) {
                    $count += count($idLists['tt_address']);
                }
                if (is_array($idLists['fe_users'] ?? false)) {
                    $count += count($idLists['fe_users']);
                }
                if (is_array($idLists['PLAINLIST'] ?? false)) {
                    $count += count($idLists['PLAINLIST']);
                }
                if (is_array($idLists[$this->userTable] ?? false)) {
                    $count += count($idLists[$this->userTable]);
                }
                $opt[] = '<option value="' . $group['uid'] . '">' . htmlspecialchars($group['title'] . ' (#' . $count . ')') . '</option>';
                $lastGroup = $group;
            }
        }
        $groupInput = '';
        // added disabled. see hook
        if (count($opt) === 0) {
            $message = ViewUtility::getFlashMessage(
                LanguageUtility::getLL('error.no_recipient_groups_found'),
                '',
                AbstractMessage::ERROR
            );
            $this->messageQueue->addMessage($message);
        } else {
            if (count($opt) === 1) {
                if (!$hookSelectDisabled) {
                    $groupInput .= '<input type="hidden" name="mailgroup_uid[]" value="' . $lastGroup['uid'] . '" />';
                }
                $groupInput .= '<ul><li>' . htmlentities($lastGroup['title']) . '</li></ul>';
                if ($hookSelectDisabled) {
                    $groupInput .= '<em>disabled</em>';
                }
            } else {
                $groupInput = '<select class="form-control" size="20" multiple="multiple" name="mailgroup_uid[]" ' . ($hookSelectDisabled ? 'disabled' : '') . '>' . implode(chr(10),
                        $opt) . '</select>';
            }
        }

        return [
            'mailGroups' => $mailGroups,
            'id' => $this->id,
            'sys_dmail_uid' => $this->sys_dmail_uid,
            'groupInput' => $groupInput,
            'hookContents' => $hookContents, // put content from hook
            'send_mail_datetime_hr' => strftime('%H:%M %d-%m-%Y', time()),
            'send_mail_datetime' => strftime('%H:%M %d-%m-%Y', time()),
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getCategoryData(array $mailData, array $indata): array|string
    {
        $categoryData = [
            'title' => LanguageUtility::getLL('nl_cat'),
            'subtitle' => '',
            'rowsFound' => false,
            'rows' => [],
            'pages_uid' => $this->pages_uid,
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
            // $dataHandler->stripslashes_values = 0;
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($this->pages_uid);
            $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
        }

        // @TODO Perhaps we should here check if TV is installed and fetch content from that instead of the old Columns...
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->selectTtContentByPidAndSysLanguageUid(
            (int)$this->pages_uid,
            (int)$mailData['sys_language_uid']
        );

        if (empty($rows)) {
            $categoryData['subtitle'] = LanguageUtility::getLL('nl_cat_msg1');
            return $categoryData;
        }
        $categoryData['subtitle'] = BackendUtility::cshItem($this->cshTable, 'assign_categories');
        $categoryData['rowsFound'] = true;

        $colPosVal = 99;
        $ttContentCategoryMmRepository = GeneralUtility::makeInstance(TtContentCategoryMmRepository::class);
        foreach ($rows as $mailData) {
            $categoriesRow = [];
            $resCat = $ttContentCategoryMmRepository->selectUidForeignByUid($mailData['uid']);

            foreach ($resCat as $rowCat) {
                $categoriesRow[] = (int)$rowCat['uid_foreign'];
            }

            if ($colPosVal != $mailData['colPos']) {
                $categoryData['rows'][] = [
                    'separator' => true,
                    'bgcolor' => '#f00',
                    'title' => LanguageUtility::getLL('nl_l_column'),
                    'value' => BackendUtility::getProcessedValue('tt_content', 'colPos', $mailData['colPos']),
                ];
                $colPosVal = $mailData['colPos'];
            }

            $ttContentCategories = RepositoryUtility::makeCategories('tt_content', $mailData, $this->sys_language_uid);
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
                'uid' => $mailData['uid'],
                'icon' => $this->iconFactory->getIconForRecord('tt_content', $mailData, Icon::SIZE_SMALL),
                'header' => $mailData['header'],
                'CType' => $mailData['CType'],
                'list_type' => $mailData['list_type'],
                'bodytext' => empty($mailData['bodytext']) ? '' : GeneralUtility::fixed_lgd_cs(strip_tags($mailData['bodytext']), 200),
                'color' => $mailData['module_sys_dmail_category'] ? 'red' : 'green',
                'labelOnlyAll' => $mailData['module_sys_dmail_category'] ? LanguageUtility::getLL('nl_l_ONLY') : LanguageUtility::getLL('nl_l_ALL'),
                'checkboxes' => $cboxes,
            ];
        }
        return $categoryData;
    }

    /**
     * Create a mail record from an internal page
     * @param int $pageUid The page ID
     * @param array $parameters The mail parameters
     * @param int $sysLanguageUid
     * @return int|bool new record uid or FALSE if failed
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws InvalidConfigurationTypeException
     */
    public function createMailRecordFromInternalPage(int $pageUid, array $parameters, int $sysLanguageUid = 0): bool|int
    {
        $result = false;

        $newRecord = [
            'type' => 0,
            'pid' => $parameters['pid'] ?? 0,
            'from_email' => $parameters['from_email'] ?? '',
            'from_name' => $parameters['from_name'] ?? '',
            'replyto_email' => $parameters['replyto_email'] ?? '',
            'replyto_name' => $parameters['replyto_name'] ?? '',
            'return_path' => $parameters['return_path'] ?? '',
            'priority' => $parameters['priority'] ?? 0,
            'use_rdct' => (!empty($parameters['use_rdct']) ? $parameters['use_rdct'] : 0), /*$parameters['use_rdct'],*/
            'long_link_mode' => (!empty($parameters['long_link_mode']) ? $parameters['long_link_mode'] : 0),//$parameters['long_link_mode'],
            'organisation' => $parameters['organisation'] ?? '',
            'authcode_fieldList' => $parameters['authcode_fieldList'] ?? '',
            'sendOptions' => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url' => $this->getUrlBase($pageUid),
            'sys_language_uid' => $sysLanguageUid,
            'attachment' => '',
            'mailContent' => '',
        ];

        if ($newRecord['sys_language_uid'] > 0) {
            $langParam = $this->getLanguageParam($newRecord['sys_language_uid'], $parameters);
            $parameters['plainParams'] .= $langParam;
            $parameters['HTMLParams'] .= $langParam;
        }

        // If params set, set default values:
        $paramsToOverride = ['sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams'];
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }

        $pageRecord = BackendUtility::getRecord('pages', $pageUid);
        // Fetch page title from translated page
        if ($newRecord['sys_language_uid'] > 0) {
            $pageRecordOverlay = GeneralUtility::makeInstance(PagesRepository::class)->selectTitleTranslatedPage($pageUid, (int)$newRecord['sys_language_uid']);
            if (is_array($pageRecordOverlay)) {
                $pageRecord['title'] = $pageRecordOverlay['title'];
            }
        }

        if ($pageRecord['doktype']) {
            $newRecord['subject'] = $pageRecord['title'];
            $newRecord['page'] = $pageRecord['uid'];
            $newRecord['charset'] = ConfigurationUtility::getCharacterSet();
        }

        // save to database
        if ($newRecord['page'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord,
                ],
            ];

            /* @var $dataHandler DataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            $result = $dataHandler->substNEWwithIDs['NEW'];
        } else {
            if (!$newRecord['sendOptions']) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Creates a mail record from an external url
     * @param string $subject Subject of the newsletter
     * @param string $externalUrlHtml Link to the HTML version
     * @param string $externalUrlPlain Linkt to the text version
     * @param array $parameters Additional newsletter parameters
     *
     * @return int|bool Error or warning message produced during the process
     * @throws SiteNotFoundException
     */
    public function createMailRecordFromExternalUrls(string $subject, string $externalUrlHtml, string $externalUrlPlain, array $parameters): bool|int
    {
        $newRecord = [
            'type' => 1,
            'pid' => $parameters['pid'] ?? 0,
            'subject' => $subject,
            'from_email' => $parameters['from_email'] ?? '',
            'from_name' => $parameters['from_name'] ?? '',
            'replyto_email' => $parameters['replyto_email'] ?? '',
            'replyto_name' => $parameters['replyto_name'] ?? '',
            'return_path' => $parameters['return_path'] ?? '',
            'priority' => $parameters['priority'] ?? 0,
            'use_rdct' => (!empty($parameters['use_rdct']) ? $parameters['use_rdct'] : 0),
            'long_link_mode' => $parameters['long_link_mode'] ?? '',
            'organisation' => $parameters['organisation'] ?? '',
            'authcode_fieldList' => $parameters['authcode_fieldList'] ?? '',
            'sendOptions' => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url' => $this->getUrlBase((int)($parameters['page'] ?? 0)),
        ];

        // If params set, set default values:
        $paramsToOverride = ['sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams'];
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }

        $urlParts = @parse_url($externalUrlPlain);
        // No plain text url
        if (!$externalUrlPlain || $urlParts === false || !$urlParts['host']) {
            $newRecord['plainParams'] = '';
            $newRecord['sendOptions'] &= 254;
        } else {
            $newRecord['plainParams'] = $externalUrlPlain;
        }

        // No html url
        $urlParts = @parse_url($externalUrlHtml);
        if (!$externalUrlHtml || $urlParts === false || !$urlParts['host']) {
            $newRecord['sendOptions'] &= 253;
        } else {
            $newRecord['HTMLParams'] = $externalUrlHtml;
        }

        // save to database
        if ($newRecord['pid'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord,
                ],
            ];

            /* @var $dataHandler DataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            return $dataHandler->substNEWwithIDs['NEW'];
        }

        return false;
    }

    /**
     * Get wizard step uri
     *
     * @param string $step
     * @param array $parameters
     * @return Uri the link
     * @throws RouteNotFoundException
     */
    protected function getWizardStepUri(string $step = Constants::WIZARD_STEP_OVERVIEW, array $parameters = []): Uri
    {
        $parameters = array_merge(['id' => $this->id], $parameters);
        if ($step) {
            $parameters['cmd'] = $step;
        }

        return $this->buildUriFromRoute($this->route, $parameters);
    }

    /**
     * Create delete link with trash icon
     *
     * @param int $uid Uid of the record
     *
     * @return Uri|null link with the trash icon
     * @throws RouteNotFoundException
     */
    protected function getDeleteMailUri(int $uid): ?Uri
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);

        if (!$dmail['scheduled_begin']) {
            return $this->buildUriFromRoute(
                $this->route,
                [
                    'id' => $this->id,
                    'uid' => $uid,
                    'cmd' => 'delete',
                ]
            );
        }

        return null;
    }

    /**
     * Get language param
     * @param int $sysLanguageUid
     * @param array $params direct_mail settings
     * @return string
     * todo use site api?
     */
    public function getLanguageParam(int $sysLanguageUid, array $params): string
    {
        return $params['langParams.'][$sysLanguageUid] ?? '&L=' . $sysLanguageUid;
    }

    /**
     * Get the base URL
     *
     * @param int $pageId
     * @return string
     * @throws SiteNotFoundException
     */
    protected function getUrlBase(int $pageId): string
    {
        if ($pageId > 0) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            if (!empty($siteFinder->getAllSites())) {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();

                return sprintf('%s://%s', $base->getScheme(), $base->getHost());
            } else {
                return ''; // No site found in root line of pageId
            }
        }

        return ''; // No valid pageId
    }
}
