<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Domain\Repository\CategoryRepository;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Service\MailerService;
use MEDIAESSENZ\Mail\Service\ReportService;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
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
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

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

    /**
     * Constructor Method
     */
    public function __construct(
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected PageRenderer          $pageRenderer,
        protected SiteFinder            $siteFinder,
        protected ReportService         $reportService,
        protected MailerService         $mailerService,
        protected RecipientService      $recipientService,
        protected MailRepository        $mailRepository,
        protected GroupRepository       $groupRepository,
        protected LogRepository         $logRepository,
        protected PageRepository        $pageRepository,
        protected CategoryRepository    $categoryRepository,
        protected IconFactory           $iconFactory,
        protected UriBuilder            $backendUriBuilder
    ) {
        $this->userTSConfiguration = TypoScriptUtility::getUserTSConfig()['tx_mail.'] ?? [];
        try {
            $hideNavigation = (ConfigurationUtility::getExtensionConfiguration('hideNavigation') && ($this->userTSConfiguration['mailModulePageId'] ?? false)) || ConfigurationUtility::getExtensionConfiguration('mailModulePageId');
            if (!$hideNavigation) {
                $this->id = (int)GeneralUtility::_GP('id');
            } else {
                if (ConfigurationUtility::getExtensionConfiguration('hideNavigation')) {
                    $this->id = (int)$this->userTSConfiguration['mailModulePageId'];
                } else {
                    $this->id = (int)(ConfigurationUtility::getExtensionConfiguration('mailModulePageId') ?: GeneralUtility::_GP('id'));
                }
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
            $this->id = (int)GeneralUtility::_GP('id');
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
    }

    public function initializeAction()
    {
        $notifications = $this->getFlashMessageQueue(ViewUtility::NOTIFICATIONS)->getAllMessagesAndFlush();
        if ($notifications) {
            foreach ($notifications as $notification) {
                $this->addJsNotification($notification->getMessage(), $notification->getTitle(), $notification->getSeverity());
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
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
        $this->pageRenderer->addJsInlineCode(ViewUtility::NOTIFICATIONS . $this->notification, 'top.TYPO3.Notification.' . ($severities[$severity] ?? 'success') . '(\'' . $title . '\', \'' . ($message ?? '') . '\');');
        $this->notification ++;
    }
}
