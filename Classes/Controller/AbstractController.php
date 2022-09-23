<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Service\MailerService;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

abstract class AbstractController
{
    protected int $id = 0;
    protected int $pageUid = 0;
    protected int $mailUid = 0;
    protected int $sysLanguageUid = 0;
    protected array|false $pageInfo = false;
    protected bool $access = false;
    protected string $siteIdentifier;
    protected string $cmd = '';

    protected int $backendUserId = 0;
    protected string $backendUserName = '';
    protected string $backendUserEmail = '';
    protected string $backendUserPermissions = '';

    protected array $pageTSConfiguration = [];
    protected array $implodedParams = [];
    protected string $userTable = '';
    protected array $allowedTables = [];

    protected ModuleTemplate $moduleTemplate;
    protected IconFactory $iconFactory;
    protected PageRenderer $pageRenderer;
    protected SiteFinder $siteFinder;
    protected MailerService $mailerService;
    protected RecipientService $recipientService;
    protected EventDispatcherInterface $eventDispatcher;
    protected StandaloneView $view;
    protected FlashMessageQueue $messageQueue;

    /**
     * Constructor Method
     */
    public function __construct(
        ModuleTemplate $moduleTemplate = null,
        IconFactory    $iconFactory = null,
        PageRenderer   $pageRenderer = null,
        StandaloneView  $view = null,
        SiteFinder     $siteFinder = null,
        MailerService  $mailerService = null,
        RecipientService $recipientService = null,
        EventDispatcherInterface $eventDispatcher = null
    )
    {
        $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->iconFactory = $iconFactory ?? GeneralUtility::makeInstance(IconFactory::class);
        $this->pageRenderer = $pageRenderer ?? GeneralUtility::makeInstance(PageRenderer::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->mailerService = $mailerService ?? GeneralUtility::makeInstance(MailerService::class);
        $this->recipientService = $recipientService ?? GeneralUtility::makeInstance(RecipientService::class);
        $this->recipientService->setPageId($this->id);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $this->view = $view ?? GeneralUtility::makeInstance(StandaloneView::class);
        $this->view->setTemplateRootPaths(['EXT:mail/Resources/Private/Templates/']);
        $this->view->setPartialRootPaths(['EXT:mail/Resources/Private/Partials/']);
        $this->view->setLayoutRootPaths(['EXT:mail/Resources/Private/Layouts/']);
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_mod2-6.xlf');
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
        $this->backendUserName = BackendUserUtility::getBackendUser()->user['realName'] ?? '';
        $this->backendUserEmail = BackendUserUtility::getBackendUser()->user['email'] ?? '';
        $this->backendUserId = BackendUserUtility::getBackendUser()->user['uid'] ?? '';
        $this->view->assignMultiple([
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ]
        ]);
    }

    protected function init(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->cmd = (string)($parsedBody['cmd'] ?? $queryParams['cmd'] ?? '');
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

        if (array_key_exists('userTable', $this->pageTSConfiguration) && isset($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']]) && is_array($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']])) {
            $this->userTable = $this->pageTSConfiguration['userTable'];
            $this->allowedTables[] = $this->userTable;
        }

        $this->messageQueue = ViewUtility::getFlashMessageQueue();
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
     * @throws RouteNotFoundException
     */
    protected function buildUriFromRoute($name, $parameters = []): Uri
    {
        return GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute(
            $name,
            $parameters
        );
    }

    protected function getTempPath(): string
    {
        return Environment::getPublicPath() . '/typo3temp/';
    }

    protected function getDmailerLogFilePath(): string
    {
        return $this->getTempPath() . 'tx_directmail_dmailer_log.txt';
    }

    protected function getDmailerLockFilePath(): string
    {
        return $this->getTempPath() . 'tx_directmail_cron.lock';
    }

    protected function getIconActionsOpen(): Icon
    {
        return $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL);
    }

    /**
     * Prepare DB record
     *
     * @param array $listArr All DB records to be formated
     * @param string $table Table name
     *
     * @return    array        list of record
     * @throws RouteNotFoundException
     */
    protected function getRecordList(array $listArr, string $table): array
    {
        $lang = LanguageUtility::getLanguageService();
        $output = [
            'title' => $lang->getLL('dmail_number_records'),
            'editLinkTitle' => $lang->getLL('dmail_edit'),
            'actionsOpen' => $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL),
            'counter' => is_array($listArr) ? count($listArr) : 0,
            'rows' => [],
        ];

        $isAllowedDisplayTable = BackendUserUtility::getBackendUser()->check('tables_select', $table);
        $isAllowedEditTable = BackendUserUtility::getBackendUser()->check('tables_modify', $table);

        if (is_array($listArr)) {
            $notAllowedPlaceholder = $lang->getLL('mailgroup_table_disallowed_placeholder');
            $tableIcon = $this->iconFactory->getIconForRecord($table, []);
            foreach ($listArr as $row) {
                $editLink = '';
                if ($row['uid'] && $isAllowedEditTable) {
                    $urlParameters = [
                        'edit' => [
                            $table => [
                                $row['uid'] => 'edit',
                            ],
                        ],
                        'returnUrl' => $this->requestUri,
                    ];

                    $editLink = $this->buildUriFromRoute('record_edit', $urlParameters);
                }

                $output['rows'][] = [
                    'icon' => $tableIcon,
                    'editLink' => $editLink,
                    'email' => $isAllowedDisplayTable ? htmlspecialchars($row['email']) : $notAllowedPlaceholder,
                    'name' => $isAllowedDisplayTable ? htmlspecialchars($row['name']) : '',
                ];
            }
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
