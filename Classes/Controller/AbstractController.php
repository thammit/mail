<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\CategoryRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Service\MailerService;
use MEDIAESSENZ\Mail\Service\ReportService;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

abstract class AbstractController extends ActionController
{
    protected int $id = 0;
    protected array|false $pageInfo = false;
    protected ?Site $site;
    protected string $siteIdentifier;
    protected string $backendUserPermissions = '';
    protected array $pageTSConfiguration = [];
    protected array $userTSConfiguration = [];
    protected array $recipientSources = [];
    protected array $implodedParams = [];
    protected array $allowedTables = [];
    protected ?ModuleTemplate $moduleTemplate = null;
    protected int $notification = 1;

    protected ?ModuleTemplateFactory $moduleTemplateFactory = null;
    protected ?PageRenderer $pageRenderer = null;
    protected ?SiteFinder $siteFinder = null;
    protected ?ReportService $reportService;
    protected ?MailerService $mailerService = null;
    protected ?RecipientService $recipientService = null;
    protected ?MailRepository $mailRepository = null;
    protected ?GroupRepository $groupRepository = null;
    protected ?LogRepository $logRepository = null;
    protected ?PageRepository $pageRepository = null;
    protected ?CategoryRepository $categoryRepository = null;
    protected ?IconFactory $iconFactory = null;
    protected ?UriBuilder $backendUriBuilder = null;

    protected int $typo3MajorVersion = 11;
    protected bool $ttAddressIsLoaded = false;
    protected bool $jumpurlIsLoaded = false;

    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @return void
     */
    public function injectModuleTemplateFactory(ModuleTemplateFactory $moduleTemplateFactory): void
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * @param PageRenderer $pageRenderer
     * @return void
     */
    public function injectPageRenderer(PageRenderer $pageRenderer): void
    {
        $this->pageRenderer = $pageRenderer;
    }

    /**
     * @param SiteFinder $siteFinder
     * @return void
     */
    public function injectSiteFinder(SiteFinder $siteFinder): void
    {
        $this->siteFinder = $siteFinder;
    }

    /**
     * @param ReportService $reportService
     * @return void
     */
    public function injectReportService(ReportService $reportService): void
    {
        $this->reportService = $reportService;
    }

    /**
     * @param MailerService $mailerService
     * @return void
     */
    public function injectMailerService(MailerService $mailerService): void
    {
        $this->mailerService = $mailerService;
    }

    /**
     * @param RecipientService $recipientService
     * @return void
     */
    public function injectRecipientService(RecipientService $recipientService): void
    {
        $this->recipientService = $recipientService;
    }

    /**
     * @param MailRepository $mailRepository
     * @return void
     */
    public function injectMailRepository(MailRepository $mailRepository): void
    {
        $this->mailRepository = $mailRepository;
    }

    /**
     * @param GroupRepository $groupRepository
     * @return void
     */
    public function injectGroupRepository(GroupRepository $groupRepository): void
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * @param LogRepository $logRepository
     * @return void
     */
    public function injectLogRepository(LogRepository $logRepository): void
    {
        $this->logRepository = $logRepository;
    }

    /**
     * @param PageRepository $pageRepository
     * @return void
     */
    public function injectPageRepository(PageRepository $pageRepository): void
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * @param CategoryRepository $categoryRepository
     * @return void
     */
    public function injectCategoryRepository(CategoryRepository $categoryRepository): void
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param IconFactory $iconFactory
     * @return void
     */
    public function injectIconFactory(IconFactory $iconFactory): void
    {
        $this->iconFactory = $iconFactory;
    }

    /**
     * @param UriBuilder $uriBuilder
     * @return void
     */
    public function injectUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->backendUriBuilder = $uriBuilder;
    }

    public function initializeAction(): void
    {
        $this->typo3MajorVersion = (new Typo3Version())->getMajorVersion();

        $this->ttAddressIsLoaded = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_address');
        $this->jumpurlIsLoaded = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('jumpurl');

        $this->id = (int)($this->request->getParsedBody()['id'] ?? $this->request->getQueryParams()['id'] ?? 0);

        $this->userTSConfiguration = TypoScriptUtility::getUserTSConfig()['tx_mail.'] ?? [];

        try {
            if (($this->userTSConfiguration['mailModulePageId'] ?? false) || ConfigurationUtility::getExtensionConfiguration('mailModulePageId')) {
                // if mailModulePageId was set in extension configuration -> use it as page id ...
                $mailModulePageUid = (int)ConfigurationUtility::getExtensionConfiguration('mailModulePageId');
                if ($this->userTSConfiguration['mailModulePageId'] ?? false) {
                    // if mailModulePageId was set in user ts config -> use it as page id ...
                    $mailModulePageUid = (int)$this->userTSConfiguration['mailModulePageId'];
                }
                $mailModulePageUids = GeneralUtility::makeInstance(PagesRepository::class)->findMailModulePageUids();
                if (is_array($mailModulePageUids) && count($mailModulePageUids) > 0 && in_array($mailModulePageUid, $mailModulePageUids)) {
                    // ... but only if page is mail module
                    $this->id = $mailModulePageUid;
                }
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
        }
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        try {
            $this->site = $this->siteFinder->getSiteByPageId($this->id);
            $this->siteIdentifier = $this->site->getIdentifier();
            $this->mailerService->setSiteIdentifier($this->siteIdentifier);
            $this->recipientSources = ConfigurationUtility::getRecipientSources($this->site->getConfiguration());
            $this->recipientService->init($this->recipientSources);
        } catch (SiteNotFoundException $e) {
            $this->siteIdentifier = '';
        }

        $this->backendUserPermissions = BackendUserUtility::backendUserPermissions();
        $this->pageInfo = BackendUtility::readPageAccess($this->id, $this->backendUserPermissions);

        // get the config from pageTS
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['mail.'] ?? [];
        $this->implodedParams = TypoScriptUtility::implodeTSParams($this->pageTSConfiguration);
        $this->pageTSConfiguration['pid'] = $this->id;

        $notifications = $this->getFlashMessageQueue(ViewUtility::NOTIFICATIONS)->getAllMessagesAndFlush();
        if ($notifications) {
            foreach ($notifications as $notification) {
                $this->addJsNotification($notification->getMessage(), $notification->getTitle(), $notification->getSeverity());
            }
        }
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        parent::initializeAction();
    }

    public function noValidPageSelectedAction(): ResponseInterface
    {
        ViewUtility::addFlashMessageWarning(LanguageUtility::getLL('mail.wizard.notification.noPageSelected.message'),
            LanguageUtility::getLL('mail.wizard.notification.noPageSelected.title'));

        if ($this->typo3MajorVersion < 12) {
            $this->view->assign('layoutSuffix', 'V11');
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }
        return $this->moduleTemplate->renderResponse('Backend/NoValidPageSelected');
    }

    protected function handleNoMailModulePageRedirect(): ResponseInterface
    {
        $mailModulePageId = BackendDataUtility::getClosestMailModulePageId($this->id);
        if ($mailModulePageId) {
            if ($this->typo3MajorVersion < 12) {
                // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                $_GET['id'] = $mailModulePageId;
            }
            return $this->redirect('index', null, null, ['id' => $mailModulePageId]);
        }
        return $this->redirect('noValidPageSelected');
    }

    protected function getDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
     * @param string $message
     * @param string $title
     * @param int|ContextualFeedbackSeverity|null $severity
     * @return void
     */
    protected function addJsNotification(string $message, string $title = '', int|ContextualFeedbackSeverity $severity = null): void
    {
        if ($this->typo3MajorVersion < 12) {
            $severityType = match ($severity) {
                AbstractMessage::NOTICE => 'notice',
                AbstractMessage::INFO => 'info',
                AbstractMessage::WARNING => 'warning',
                AbstractMessage::ERROR => 'error',
                default => 'success',
            };;
        } else {
            $severityType = match ($severity) {
                ContextualFeedbackSeverity::NOTICE => 'notice',
                ContextualFeedbackSeverity::INFO => 'info',
                ContextualFeedbackSeverity::WARNING => 'warning',
                ContextualFeedbackSeverity::ERROR => 'error',
                default => 'success',
            };;
        }
        $this->pageRenderer->addJsInlineCode(ViewUtility::NOTIFICATIONS . $this->notification,
            "top.TYPO3.Notification.$severityType('$title', '$message')", false, false, true);
        $this->notification++;
    }

    /**
     * @param int $refreshRate
     * @return void
     */
    protected function addQueueRefresher(int $refreshRate): void
    {
        $this->pageRenderer->addInlineSetting('Mail', 'refreshRate', $refreshRate);
        if ($this->typo3MajorVersion < 12) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/QueueRefresher');
        } else {
            $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/queue-refresher.js');
        }
    }

    /**
     * @param null|string $json
     * @param int $errorCode
     * @return ResponseInterface
     */
    protected function jsonErrorResponse(string $json = null, int $errorCode = 400): ResponseInterface
    {
        return $this->responseFactory->createResponse($errorCode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream($json ?? $this->view->render()));
    }

    /**
     * @param Mail $mail
     * @param bool $forceReadOnly
     * @return void
     * @throws NoSuchPropertyException
     * @throws UnknownClassException
     */
    protected function assignFieldGroups(Mail $mail, bool $forceReadOnly = false): void
    {
        $fieldGroups = [];
        $groups = [
            'general' => ['subject', 'fromEmail', 'fromName', 'organisation', 'attachment'],
            'headers' => ['replyToEmail', 'replyToName', 'returnPath', 'priority'],
            'content' => ['sendOptions', 'includeMedia', 'redirect', 'redirectAll', 'authCodeFields'],
            'source' => ['type', 'renderedSize', 'page', 'sysLanguageUid', 'plainParams', 'htmlParams'],
        ];

        if (isset($this->userTSConfiguration['settings.'])) {
            foreach ($this->userTSConfiguration['settings.'] as $groupName => $fields) {
                $fieldsArray = GeneralUtility::trimExplode(',', $fields, true);
                if ($fieldsArray) {
                    $groups[$groupName] = $fieldsArray;
                } else {
                    unset($groups[$groupName]);
                }
            }
        }

        if ($this->userTSConfiguration['settingsWithoutTabs'] ?? count($groups) === 1) {
            if ($this->typo3MajorVersion < 12) {
                $this->view->assign('settingsWithoutTabs', true);
            } else {
                $this->moduleTemplate->assign('settingsWithoutTabs', true);
            }
        }

        if ($forceReadOnly || isset($this->userTSConfiguration['hideEditAllSettingsButton'])) {
            if ($this->typo3MajorVersion < 12) {
                $this->view->assign('hideEditAllSettingsButton',
                    $forceReadOnly || $this->userTSConfiguration['hideEditAllSettingsButton']);
            } else {
                $this->moduleTemplate->assign('hideEditAllSettingsButton',
                    $forceReadOnly || $this->userTSConfiguration['hideEditAllSettingsButton']);
            }
        }

        $readOnly = ['type', 'renderedSize'];
        if (isset($this->userTSConfiguration['readOnlySettings'])) {
            $readOnly = GeneralUtility::trimExplode(',', $this->userTSConfiguration['readOnlySettings'], true);
        }

        if ($mail->isExternal()) {
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'sysLanguageUid');
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'page');
            if (!$mail->getHtmlParams() || $mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'htmlParams');
            }
            if (!$mail->getPlainParams() || $mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'plainParams');
            }
            if ($mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'includeMedia');
            }
        }

        if (!$mail->isPlain() || ($forceReadOnly && !$mail->getPlainParams())) {
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'plainParams');
        }

        if (!$mail->isHtml() || ($forceReadOnly && !$mail->getHtmlParams())) {
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'htmlParams');
        }

        $className = get_class($mail);
        $dataMapFactory = GeneralUtility::makeInstance(DataMapFactory::class);
        $dataMap = $dataMapFactory->buildDataMap($className);
        $tableName = $dataMap->getTableName();
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $classSchema = $reflectionService->getClassSchema($className);
        $backendUserIsAllowedToEditMailSettingsRecord = BackendUserUtility::getBackendUser()->check('tables_modify', $tableName);
        if ($forceReadOnly) {
            $backendUserIsAllowedToEditMailSettingsRecord = false;
        }

        foreach ($groups as $groupName => $properties) {
            foreach ($properties as $property) {
                $getter = 'get' . ucfirst($property);
                if (!method_exists($mail, $getter)) {
                    $getter = 'is' . ucfirst($property);
                }
                if (method_exists($mail, $getter)) {
                    $columnName = $dataMap->getColumnMap($classSchema->getProperty($property)->getName())->getColumnName();
                    $rawValue = $mail->$getter();
                    if ($rawValue instanceof SendFormat) {
                        $rawValue = (string)$rawValue;
                    }
                    $value = match ($property) {
                        'type' => ($mail->isQuickMail() ? LanguageUtility::getLL('mail.type.quickMail') : (BackendUtility::getProcessedValue($tableName,
                            $columnName, $rawValue) ?: '')),
                        'sysLanguageUid' => $this->site->getLanguageById((int)$rawValue)->getTitle(),
                        'renderedSize' => GeneralUtility::formatSize((int)$rawValue, 'si') . 'B',
                        'attachment' => $mail->getAttachmentCsv(),
                        'page' => (BackendUtility::getProcessedValue($tableName, $columnName, $rawValue) ?: '') . ($mail->isInternal() ? ' [' . $rawValue . ']' : ''),
                        default => BackendUtility::getProcessedValue($tableName, $columnName, $rawValue) ?: '',
                    };
                    $fieldGroups[$groupName][$property] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField($columnName, $tableName),
                        'value' => $value,
                        'edit' => !$backendUserIsAllowedToEditMailSettingsRecord || in_array($property, $readOnly) ? false : GeneralUtility::camelCaseToLowerCaseUnderscored($property),
                    ];
                }
            }
        }
        if ($this->typo3MajorVersion < 12) {
            $this->view->assign('fieldGroups', $fieldGroups);
        } else {
            $this->moduleTemplate->assign('fieldGroups', $fieldGroups);
        }
    }
}
