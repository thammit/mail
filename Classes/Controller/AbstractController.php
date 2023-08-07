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
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
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
    protected ModuleTemplate $moduleTemplate;
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
            $this->recipientSources = $this->site->getConfiguration()['mail']['recipientSources'] ?? ConfigurationUtility::getDefaultRecipientSources() ?? [];
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
                $this->addJsNotification($notification->getMessage(), $notification->getTitle(), $this->typo3MajorVersion < 12 ? $notification->getSeverity() : $notification->getSeverity()->value);
            }
        }
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        parent::initializeAction();
    }

    protected function getDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
     * @param string $message
     * @param string $title
     * @param int $severity
     * @return void
     */
    protected function addJsNotification(string $message, string $title = '', int $severity = AbstractMessage::OK): void
    {
        $severities = [
            AbstractMessage::NOTICE => 'notice',
            AbstractMessage::INFO => 'info',
            AbstractMessage::OK => 'success',
            AbstractMessage::WARNING => 'warning',
            AbstractMessage::ERROR => 'error',
        ];
//        if ($this->typo3MajorVersion < 12) {
//            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
//        } else {
//            $this->pageRenderer->loadJavaScriptModule('@typo3/backend/notification.js');
//        }
        $this->pageRenderer->addJsInlineCode(ViewUtility::NOTIFICATIONS . $this->notification,
            'top.TYPO3.Notification.' . ($severities[$severity] ?? 'success') . '(\'' . $title . '\', \'' . $message . '\');');
        $this->notification++;
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
            $this->view->assign('settingsWithoutTabs', true);
        }

        if ($forceReadOnly || isset($this->userTSConfiguration['hideEditAllSettingsButton'])) {
            $this->view->assign('hideEditAllSettingsButton', $forceReadOnly || $this->userTSConfiguration['hideEditAllSettingsButton']);
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

        $this->view->assign('fieldGroups', $fieldGroups);
    }
}
