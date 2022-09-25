<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtAddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
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
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;

class DmailController extends AbstractController
{
    protected string $route = 'MailNavFrame_Mail';
    protected string $moduleName = 'MailNavFrame_Mail';
    protected string $cshKey = '_MOD_MailNavFrame_Mail';
    protected string $error = '';
    protected int $currentStep = 1;
    protected bool $reset = false;
    protected int $uid = 0;
    protected bool $backButtonPressed = false;
    protected Action $currentCMD;
    protected bool $fetchAtOnce = false;
    protected array $quickMail = [];
    protected int $createMailFromPageUid = 0;
    protected int $createMailForLanguageUid = 0;
    protected string $subjectForExternalMail = '';
    protected string $externalMailHtmlUri = '';
    protected string $externalMailPlainUri = '';
    protected array $mailGroupUids = [];
    protected bool $sendTestMail = false;
    protected bool $scheduleSendAll = false;
    protected bool $saveDraft = false;
    protected bool $isTestMail = false;
    protected string $sendTestMailAddress = '';
    protected int $distributionTimeStamp = 0;
    protected string $requestUri = '';
    // protected int $tt_address_uid = 0;

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
            $this->setCurrentAction(Action::cast( Action::WIZARD_STEP_CATEGORIES));
        }

        $this->sendTestMail = (bool)($parsedBody['sendTestMail']['send'] ?? $queryParams['sendTestMail']['send'] ?? false);
        $this->sendTestMailAddress = (string)($parsedBody['sendTestMail']['address'] ?? $queryParams['sendTestMail']['address'] ?? '');
        if ($this->sendTestMail) {
            $this->setCurrentAction(Action::cast( Action::WIZARD_STEP_SEND_TEST2));
        }

        $this->backButtonPressed = (bool)($parsedBody['back'] ?? $queryParams['back'] ?? false);

        $this->currentCMD = Action::cast(($parsedBody['currentCMD'] ?? $queryParams['currentCMD'] ?? null));
        // Create mail and fetch the data
        $this->fetchAtOnce = (bool)($parsedBody['fetchAtOnce'] ?? $queryParams['fetchAtOnce'] ?? false);

        $this->createMailFromPageUid = (int)($parsedBody['createMailFromPageUid'] ?? $queryParams['createMailFromPageUid'] ?? 0);
        $this->createMailForLanguageUid = (int)($parsedBody['createMailForLanguageUid'] ?? $queryParams['createMailForLanguageUid'] ?? 0);

        $this->subjectForExternalMail = (string)($parsedBody['subjectForExternalMail'] ?? $queryParams['subjectForExternalMail'] ?? '');
        $this->externalMailHtmlUri = (string)($parsedBody['externalMailHtmlUri'] ?? $queryParams['externalMailHtmlUri'] ?? '');
        $this->externalMailPlainUri = (string)($parsedBody['externalMailPlainUri'] ?? $queryParams['externalMailPlainUri'] ?? '');

        $this->quickMail = (array)($parsedBody['quickmail'] ?? $queryParams['quickmail'] ?? []);

        $this->mailGroupUids = $parsedBody['mailGroupUid'] ?? $queryParams['mailGroupUid'] ?? [];
        $this->scheduleSendAll = (bool)($parsedBody['scheduleSendAll'] ?? $queryParams['scheduleSendAll'] ?? false);
        $this->saveDraft = (bool)($parsedBody['saveDraft'] ?? $queryParams['saveDraft'] ?? false);
        $this->isTestMail = (bool)($parsedBody['isTestMail'] ?? $queryParams['isTestMail'] ?? false);
        $this->distributionTimeStamp = strtotime((string)($parsedBody['distributionTime'] ?? $queryParams['distributionTime'] ?? '')) ?: time();
        if ($this->distributionTimeStamp < time()) {
            $this->distributionTimeStamp = time();
        }
        // $this->tt_address_uid = (int)($parsedBody['tt_address_uid'] ?? $queryParams['tt_address_uid'] ?? 0);
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

        $this->view->setTemplate('Wizard');

        // get module name of the selected page in the page tree
        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            // mail module
            if (($this->pageInfo['doktype'] ?? 0) == 254) {
                // Add module data to view
                $this->view->assignMultiple($this->getModuleData());
                if ($this->reset) {
                    return new RedirectResponse($this->buildUriFromRoute($this->route, ['id' => $this->id]));
                }
            } else {
                if ($this->id) {
                    ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_noRegular'), LanguageUtility::getLL('dmail_newsletters'));
                }
            }
        } else {
            // Todo search for dmail modules the tree up and if found open the wizard settings step of the selected page
            ViewUtility::addInfoToFlashMessageQueue('Todo search for dmail modules the tree up and if found open the wizard settings step of the selected page', 'Todo');
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
        $sysDmailRepository = GeneralUtility::makeInstance(SysDmailRepository::class);

        if ($this->action->equals(Action::DELETE_MAIL)) {
            $sysDmailRepository->delete($this->uid);
        }

        $isExternalDirectMailRecord = false;
        $mailData = [];
        if ($this->mailUid) {
            // get the data of the currently selected mail
            $mailData = $sysDmailRepository->findByUid($this->mailUid);
            $isExternalDirectMailRecord = is_array($mailData) && (int)$mailData['type'] === MailType::EXTERNAL;
        }

        $userTSConfig = TypoScriptUtility::getUserTSConfig();
        $hideCategoryStep = $isExternalDirectMailRecord || (isset($userTSConfig['tx_directmail.']['hideSteps']) && $userTSConfig['tx_directmail.']['hideSteps'] === 'cat');

        if ($this->backButtonPressed) {
            // CMD move 1 step back
            switch ((string)$this->currentCMD) {
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
                        $this->mailUid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $mailData = $sysDmailRepository->findByUid($newUid);
                        // fetch the data
                        if ($this->fetchAtOnce) {
                            $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                        }

                        $moduleData['info']['internal']['cmd'] = $nextCmd ?: Action::WIZARD_STEP_CATEGORIES;
                    } else {
                        ViewUtility::addErrorToFlashMessageQueue('Error while adding the DB set', LanguageUtility::getLL('dmail_error'));
                    }
                } // external URL
                else {
                    if ($this->subjectForExternalMail != '' && !$quickmail['send']) {
                        $newUid = $this->createMailRecordFromExternalUrls(
                            $this->subjectForExternalMail,
                            $this->externalMailHtmlUri,
                            $this->externalMailPlainUri,
                            $this->pageTSConfiguration
                        );
                        if (is_numeric($newUid)) {
                            $this->mailUid = $newUid;
                            // Read new record (necessary because TCEmain sets default field values)
                            $mailData = $sysDmailRepository->findByUid($newUid);
                            // fetch the data
                            if ($this->fetchAtOnce) {
                                $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                            }

                            $moduleData['info']['external']['cmd'] = Action::WIZARD_STEP_SEND_TEST;
                        } else {
                            // TODO: Error message - Error while adding the DB set
                            $this->error = 'no_valid_url';
                            ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_html_uri_is_invalid') . ' Requested URL: ' . $this->externalMailHtmlUri,
                                LanguageUtility::getLL('dmail_error'));
                        }
                    } // Quickmail
                    else {
                        if ($quickmail['send']) {
                            $temp = $this->createQuickMail($quickmail);
                            if (!$temp['errorTitle']) {
                                $fetchError = false;
                            }
                            if ($temp['errorTitle']) {
                                ViewUtility::addErrorToFlashMessageQueue($temp['errorText'], $temp['errorTitle']);
                            }
                            if ($temp['warningTitle']) {
                                ViewUtility::addWarningToFlashMessageQueue($temp['warningText'], $temp['warningTitle']);
                            }

                            // Todo Check if we do not need the newly created quick mail here
                            $mailData = $sysDmailRepository->findByUid($this->mailUid);

                            $moduleData['info']['quickmail']['cmd'] = Action::WIZARD_STEP_SEND_TEST;
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

                                    $moduleData['info']['dmail']['cmd'] = Action::WIZARD_STEP_SEND_TEST;

                                    // add attachment here, since attachment added in 2nd step
                                    $unserializedMailContent = unserialize(base64_decode($mailData['mailContent']));
                                    $temp = $this->compileQuickMail($mailData, $unserializedMailContent['plain']['content'] ?? '');
                                    if ($temp['errorTitle']) {
                                        ViewUtility::addErrorToFlashMessageQueue($temp['errorText'], $temp['errorTitle']);
                                    }
                                    if ($temp['warningTitle']) {
                                        ViewUtility::addWarningToFlashMessageQueue($temp['warningText'], $temp['warningTitle']);
                                    }
                                } else {
                                    if ($this->fetchAtOnce) {
                                        $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
                                    }

                                    $moduleData['info']['dmail']['cmd'] = ($mailData['type'] == 0) ? $nextCmd : Action::WIZARD_STEP_SEND_TEST;
                                }
                            }
                        }
                    }
                }

                $moduleData['navigation']['back'] = true;
                $moduleData['navigation']['next'] = true;
                $moduleData['navigation']['nextError'] = $fetchError;

                if (!$fetchError && $this->fetchAtOnce) {
                    ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_wiz2_fetch_success'));
                }
                $moduleData['info']['table'] = is_array($mailData) ? $this->getGroupedMailSettings($mailData) : '';
                $moduleData['info']['mailUid'] = $this->mailUid;
                $moduleData['info']['pageUid'] = $mailData['page'] ?: '';
                $moduleData['info']['currentCMD'] = $this->action;
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
                // $moduleData['cats']['catsForm'] = $temp['theOutput'];

                $moduleData['cats']['cmd'] = Action::WIZARD_STEP_SEND_TEST;
                $moduleData['cats']['mailUid'] = $this->mailUid;
                $moduleData['cats']['pageUid'] = $this->pageUid;
                $moduleData['cats']['currentCMD'] = $this->action;
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
                $moduleData['test']['currentCMD'] = $this->action;
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
                $moduleData['final']['currentCMD'] = $this->action;
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
                    $moduleData['default']['internal'] = [
                        'open' => $open,
                        'data' => GeneralUtility::makeInstance(PagesRepository::class)->findMailPages($this->id, $this->backendUserPermissions)
                    ];
                    break;
                case Constants::PANEL_EXTERNAL:
                    $moduleData['default']['external'] = ['open' => $open];
                    break;
                case Constants::PANEL_QUICK_MAIL:
                    $temp = $this->getQuickMailConfig();
                    $temp['open'] = $open;
                    $moduleData['default']['quickMail'] = $temp;
                    break;
                case Constants::PANEL_OPEN_STORED:
                    $moduleData['default']['openStored'] = [
                        'open' => $open,
                        'data' => GeneralUtility::makeInstance(SysDmailRepository::class)->findOpenMailsByPageId($this->id)
                    ];
                    break;
                default:
            }
        }
        return $moduleData;
    }

    /**
     * Create a mail record from an internal page
     * @param int $pageUid The page ID
     * @param array $parameters The mail parameters
     * @param int $sysLanguageUid
     * @return int|bool new record uid or FALSE if failed
     * @throws DBALException
     * @throws Exception
     * @throws InvalidConfigurationTypeException
     */
    protected function createMailRecordFromInternalPage(int $pageUid, array $parameters, int $sysLanguageUid = 0): bool|int
    {
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
            'long_link_rdct_url' => BackendDataUtility::getBaseUrl($pageUid),
            'sys_language_uid' => $sysLanguageUid,
            'attachment' => '',
            'mailContent' => '',
        ];

        if ($newRecord['sys_language_uid'] > 0) {
            $langParam = $parameters['langParams.'][$newRecord['sys_language_uid']] ?? '&L=' . $newRecord['sys_language_uid'];
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
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            return $dataHandler->substNEWwithIDs['NEW'];
        }

        return false;
    }

    /**
     * Creates a mail record from an external url
     * @param string $subject Subject of the newsletter
     * @param string $externalUrlHtml Link to the HTML version
     * @param string $externalUrlPlain Linkt to the text version
     * @param array $parameters Additional newsletter parameters
     *
     * @return int|bool Error or warning message produced during the process
     */
    protected function createMailRecordFromExternalUrls(string $subject, string $externalUrlHtml, string $externalUrlPlain, array $parameters): bool|int
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
            'long_link_rdct_url' => BackendDataUtility::getBaseUrl((int)($parameters['page'] ?? 0)),
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
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            return $dataHandler->substNEWwithIDs['NEW'];
        }

        return false;
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
     */
    protected function createQuickMail(array $indata): array|string
    {
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
        $dmail['sys_dmail']['NEW']['long_link_rdct_url'] = BackendDataUtility::getBaseUrl((int)$this->pageTSConfiguration['pid']);
        $dmail['sys_dmail']['NEW']['subject'] = $indata['subject'];
        $dmail['sys_dmail']['NEW']['type'] = 1;
        $dmail['sys_dmail']['NEW']['pid'] = $this->pageInfo['uid'];
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

        if ($dmail['sys_dmail']['NEW']['pid']) {
            $dataHandler = $this->getDataHandler();
            $dataHandler->start($dmail, []);
            $dataHandler->process_datamap();
            $this->mailUid = $dataHandler->substNEWwithIDs['NEW'];

            $row = BackendUtility::getRecord('sys_dmail', intval($this->mailUid));
            // link in the mail
            $message = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_-->' . $indata['message'] . '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';
            if ($this->pageTSConfiguration['use_rdct'] ?? false) {
                $message = MailerUtility::shortUrlsInPlainText(
                    $message,
                    $this->pageTSConfiguration['long_link_mode'] ? 0 : 76,
                    BackendDataUtility::getBaseUrl((int)$this->pageTSConfiguration['pid'])
                );
            }
            if ($indata['breakLines'] ?? false) {
                $message = wordwrap($message, 76);
            }
            // fetch functions
            return $this->compileQuickMail($row, $message);
            // end fetch function
        }

        return [];
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

            GeneralUtility::makeInstance(SysDmailRepository::class)->update($this->mailUid, [
                'issent' => 0,
                'charset' => $this->mailerService->getCharset(),
                'mailContent' => $mailContent,
                'renderedSize' => strlen($mailContent)
            ]);
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
            // $dataHandler->stripslashes_values = 0;
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($this->pageUid);
            $fetchError = $this->mailerService->assemble($mailData, $this->pageTSConfiguration);
        }

        // @TODO Perhaps we should here check if TV is installed and fetch content from that instead of the old Columns...
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->selectTtContentByPidAndSysLanguageUid(
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

            $ttContentCategories = RepositoryUtility::makeCategories('tt_content', $mailData, $this->sysLanguageUid);
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
            $testTtAddressUids = implode(',', GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_tt_address_uids']));
            $data['ttAddress'] = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForTestmail($testTtAddressUids,
                $this->backendUserPermissions);
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
            $data['mailGroups'] = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForTestmail($testMailGroupUids,
                $this->backendUserPermissions);

//            foreach ($rows as $row) {
//                $moduleUrl = $this->getWizardStepUri(Action::WIZARD_STEP_SEND_TEST2, [
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
                    LanguageUtility::getLL('send_was_sent') . ' ' . LanguageUtility::getLL('send_recipients') . ' ' . htmlspecialchars($addressList),
                    LanguageUtility::getLL('send_sending')
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
        // Setting flags and update the record:
        if ($sentFlag && $this->action === Action::WIZARD_STEP_FINAL) {

            $connection = $this->getConnection('sys_dmail');
            $connection->update(
                'sys_dmail',
                ['issent' => 1],
                ['uid' => $this->mailUid]
            );
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
            $connection = $this->getConnection('sys_dmail');
            $connection->update(
                'sys_dmail',
                $updateFields,
                ['uid' => $this->mailUid]
            );
            $sentFlag = true;
        }

        // Setting flags and update the record:
        if ($sentFlag) {

            $connection = $this->getConnection('sys_dmail');
            $connection->update(
                'sys_dmail',
                ['issent' => 1],
                ['uid' => $this->mailUid]
            );
        }
    }

    /**
     * Get external page config
     *
     * @return array config for form for inputing the external page information
     */
    /*
    protected function getExternalPageConfig(): array
    {
        return [
            'title' => 'dmail_dovsk_crFromUrl',
            'no_valid_url' => $this->error == 'no_valid_url',
        ];
    }
    */

    /**
     * Get wizard step uri
     *
     * @param string $step
     * @param array $parameters
     * @return Uri the link
     * @throws RouteNotFoundException
     */
    /*
    protected function getWizardStepUri(string $step = Constants::WIZARD_STEP_OVERVIEW, array $parameters = []): Uri
    {
        $parameters = array_merge(['id' => $this->id], $parameters);
        if ($step) {
            $parameters['cmd'] = $step;
        }

        return $this->buildUriFromRoute($this->route, $parameters);
    }
    */

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
    /*
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
    */
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
    /*
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
    */

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
    /*
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
                    $requestUri = $this->requestUri . '&cmd=send_test&sys_dmail_uid=' . $this->mailUid . '&pages_uid=' . $this->pageUid;

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
                    $moduleUrl = $this->getWizardStepUri(Action::WIZARD_STEP_SEND_TEST2, [
                        'sys_dmail_uid' => $this->mailUid,
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
    */

}
