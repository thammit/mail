<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Domain\Repository\FeUsersRepository;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtAddressRepository;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Service\MailerService;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

abstract class OldAbstractController
{
    protected int $id = 0;
    protected int $pageUid = 0;
    protected int $mailUid = 0;
    protected int $sysLanguageUid = 0;
    protected array|false $pageInfo = false;
    protected bool $access = false;
    protected string $siteIdentifier;
    protected Action $action;

    protected string $backendUserPermissions = '';

    protected array $pageTSConfiguration = [];
    protected array $implodedParams = [];
    protected string $userTable = '';
    protected array $allowedTables = [];

    protected ModuleTemplate $moduleTemplate;
    protected IconFactory $iconFactory;
    protected PageRenderer $pageRenderer;
    protected StandaloneView $view;
    protected SiteFinder $siteFinder;
    protected UriBuilder $uriBuilder;
    protected MailerService $mailerService;
    protected RecipientService $recipientService;
    protected SysDmailRepository $sysDmailRepository;
    protected SysDmailGroupRepository $sysDmailGroupRepository;
    protected SysDmailMaillogRepository $sysDmailMaillogRepository;
    protected PagesRepository $pagesRepository;
    protected TempRepository $tempRepository;
    protected TtAddressRepository $ttAddressRepository;
    protected FeUsersRepository $feUsersRepository;
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * Constructor Method
     */
    public function __construct(
        ModuleTemplate $moduleTemplate = null,
        IconFactory $iconFactory = null,
        PageRenderer $pageRenderer = null,
        StandaloneView $view = null,
        SiteFinder $siteFinder = null,
        UriBuilder $uriBuilder = null,
        MailerService $mailerService = null,
        RecipientService $recipientService = null,
        SysDmailRepository $sysDmailRepository = null,
        SysDmailGroupRepository $sysDmailGroupRepository = null,
        SysDmailMaillogRepository $sysDmailMaillogRepository = null,
        PagesRepository $pagesRepository = null,
        TempRepository $tempRepository = null,
        TtAddressRepository $ttAddressRepository = null,
        FeUsersRepository $feUsersRepository = null,
        EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->iconFactory = $iconFactory ?? GeneralUtility::makeInstance(IconFactory::class);
        $this->pageRenderer = $pageRenderer ?? GeneralUtility::makeInstance(PageRenderer::class);
        $this->view = $view ?? GeneralUtility::makeInstance(StandaloneView::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->uriBuilder = $uriBuilder ?? GeneralUtility::makeInstance(UriBuilder::class);
        $this->mailerService = $mailerService ?? GeneralUtility::makeInstance(MailerService::class);
        $this->recipientService = $recipientService ?? GeneralUtility::makeInstance(RecipientService::class);
        $this->recipientService->setPageId($this->id);
        $this->sysDmailRepository = $sysDmailRepository ?? GeneralUtility::makeInstance(SysDmailRepository::class);
        $this->sysDmailGroupRepository = $sysDmailGroupRepository ?? GeneralUtility::makeInstance(SysDmailGroupRepository::class);
        $this->sysDmailMaillogRepository = $sysDmailMaillogRepository ?? GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->tempRepository = $tempRepository ?? GeneralUtility::makeInstance(TempRepository::class);
        $this->ttAddressRepository = $ttAddressRepository ?? GeneralUtility::makeInstance(TtAddressRepository::class);
        $this->feUsersRepository = $feUsersRepository ?? GeneralUtility::makeInstance(FeUsersRepository::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);

        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_mod2-6.xlf');
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->view->setTemplateRootPaths(['EXT:mail/Resources/Private/Templates/']);
        $this->view->setPartialRootPaths(['EXT:mail/Resources/Private/Partials/']);
        $this->view->setLayoutRootPaths(['EXT:mail/Resources/Private/Layouts/']);
        $this->view->assignMultiple([
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);
    }

    protected function init(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->setCurrentAction(Action::cast($parsedBody['cmd'] ?? $queryParams['cmd'] ?? null));
        $this->pageUid = (int)($parsedBody['pageUid'] ?? $queryParams['pageUid'] ?? 0);
        $this->mailUid = (int)($parsedBody['mailUid'] ?? $queryParams['mailUid'] ?? 0);

        try {
            $this->siteIdentifier = $this->siteFinder->getSiteByPageId($this->id)->getIdentifier();
            $this->mailerService->setSiteIdentifier($this->siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $this->siteIdentifier = '';
        }

        $this->backendUserPermissions = BackendUserUtility::backendUserPermissions();
        $this->pageInfo = BackendUtility::readPageAccess($this->id, $this->backendUserPermissions);
        $this->access = $this->pageInfo !== false;

        // get the config from pageTS
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = TypoScriptUtility::implodeTSParams($this->pageTSConfiguration);
        $this->pageTSConfiguration['pid'] = $this->id;

        if (array_key_exists('userTable',
                $this->pageTSConfiguration) && isset($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']]) && is_array($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']])) {
            $this->userTable = $this->pageTSConfiguration['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
    }

    protected function backendUserHasModuleAccess(): bool
    {
        return ($this->id && $this->access) || (BackendUserUtility::isAdmin() && !$this->id);
    }

    protected function getModulName()
    {
        $module = $this->pageInfo['module'] ?? false;

        if (!$module && isset($this->pageInfo['pid'])) {
            $pidrec = BackendUtility::getRecord('pages', intval($this->pageInfo['pid']));
            $module = $pidrec['module'] ?? false;
        }

        return $module;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Action
     */
    public function getCurrentAction(): Action
    {
        return $this->action;
    }

    /**
     * @param Action $action
     */
    public function setCurrentAction(Action $action): void
    {
        $this->action = $action;
    }

    protected function getConnection(string $table): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
    }

    protected function getQueryBuilder(string $table): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }

    protected function getDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
     * Prepare DB record
     *
     * @param array $listArr All DB records to be formated
     * @param string $table Table name
     *
     * @return    array        list of record
     */
    protected function getRecordList(array $listArr, string $table): array
    {
        $isAllowedDisplayTable = BackendUserUtility::getBackendUser()->check('tables_select', $table);
        $isAllowedEditTable = BackendUserUtility::getBackendUser()->check('tables_modify', $table);
        $output = [
            'rows' => [],
            'table' => $table,
            'edit' => $isAllowedEditTable,
            'show' => $isAllowedDisplayTable,
        ];

        $notAllowedPlaceholder = LanguageUtility::getLL('mailgroup_table_disallowed_placeholder');
        foreach ($listArr as $row) {
            $output['rows'][] = [
                'uid' => $row['uid'],
                'email' => $isAllowedDisplayTable ? htmlspecialchars($row['email']) : $notAllowedPlaceholder,
                'name' => $isAllowedDisplayTable ? htmlspecialchars($row['name']) : '',
            ];
        }

        return $output;
    }

    protected function getJS($mailUid): string
    {
        return '
        script_ended = 0;
        function jumpToUrl(URL)	{
            window.location.href = URL;
        }
        function jumpToUrlD(URL) {
            window.location.href = URL+"&mailUid=' . $mailUid . '";
        }
        function toggleDisplay(toggleId, e, countBox) {
            if (!e) {
                e = window.event;
            }
            if (!document.getElementById) {
                return false;
            }

            prefix = toggleId.split("-");
            for (i=1; i<=countBox; i++){
                newToggleId = prefix[0]+"-"+i;
                body = document.getElementById(newToggleId);
                image = document.getElementById(toggleId + "_toggle"); //ConfigurationController
                //image = document.getElementById(newToggleId + "_toggle"); //DmailController
                if (newToggleId != toggleId){
                    if (body.style.display == "block"){
                        body.style.display = "none";
                        if (image) {
                            image.className = image.className.replace( /expand/ , "collapse");
                        }
                    }
                }
            }

            var body = document.getElementById(toggleId);
            if (!body) {
                return false;
            }
            var image = document.getElementById(toggleId + "_toggle");
            if (body.style.display == "none") {
                body.style.display = "block";
                if (image) {
                    image.className = image.className.replace( /collapse/ , "expand");
                }
            } else {
                body.style.display = "none";
                if (image) {
                    image.className = image.className.replace( /expand/ , "collapse");
                }
            }
            if (e) {
                // Stop the event from propagating, which
                // would cause the regular HREF link to
                // be followed, ruining our hard work.
                e.cancelBubble = true;
                if (e.stopPropagation) {
                    e.stopPropagation();
                }
            }
        }
        ';
    }
}
