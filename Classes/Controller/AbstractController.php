<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Service\MailerService;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
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
    protected ModuleTemplate $moduleTemplate;
    protected IconFactory $iconFactory;
    protected PageRenderer $pageRenderer;
    protected SiteFinder $siteFinder;
    protected MailerService $mailerService;
    protected RecipientService $recipientService;
    protected EventDispatcherInterface $eventDispatcher;
    protected StandaloneView $view;
    protected int $id = 0;
    protected string $cmd = '';
    protected int $sys_dmail_uid = 0;
    protected string $pages_uid = '';
    protected array $pageTSConfiguration = [];

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @see init()
     * @var string
     */
    protected string $backendUserPermissions = '';
    protected array $implodedParams = [];
    protected string $userTable = '';
    protected array $allowedTables = [];
    protected int $sys_language_uid = 0;
    protected array $pageinfo = [];
    protected bool $access = false;
    protected FlashMessageQueue $messageQueue;
    protected string $siteIdentifier;

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
        MailerUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_mod2-6.xlf');
        MailerUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
    }

    protected function init(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->cmd = (string)($parsedBody['cmd'] ?? $queryParams['cmd'] ?? '');
        $this->pages_uid = (string)($parsedBody['pages_uid'] ?? $queryParams['pages_uid'] ?? '');
        $this->sys_dmail_uid = (int)($parsedBody['sys_dmail_uid'] ?? $queryParams['sys_dmail_uid'] ?? 0);

        try {
            $this->siteIdentifier = $this->siteFinder->getSiteByPageId($this->id)->getIdentifier();
            $this->mailerService->setSiteIdentifier($this->siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $this->siteIdentifier = '';
        }

        $this->backendUserPermissions = MailerUtility::backendUserPermissions();
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->backendUserPermissions);

        $this->access = is_array($this->pageinfo) ? true : false;

        // get the config from pageTS
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = TypoScriptUtility::implodeTSParams($this->pageTSConfiguration);

        if (array_key_exists('userTable', $this->pageTSConfiguration) && isset($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']]) && is_array($GLOBALS['TCA'][$this->pageTSConfiguration['userTable']])) {
            $this->userTable = $this->pageTSConfiguration['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
        // initialize backend user language
        //$this->sys_language_uid = 0; //@TODO

        $this->messageQueue = MailerUtility::getFlashMessageQueue();
    }

    protected function getModulName()
    {
        $module = $this->pageinfo['module'] ?? false;

        if (!$module && isset($this->pageinfo['pid'])) {
            $pidrec = BackendUtility::getRecord('pages', intval($this->pageinfo['pid']));
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
        $lang = MailerUtility::getLanguageService();
        $output = [
            'title' => $lang->getLL('dmail_number_records'),
            'editLinkTitle' => $lang->getLL('dmail_edit'),
            'actionsOpen' => $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL),
            'counter' => is_array($listArr) ? count($listArr) : 0,
            'rows' => [],
        ];

        $isAllowedDisplayTable = MailerUtility::getBackendUser()->check('tables_select', $table);
        $isAllowedEditTable = MailerUtility::getBackendUser()->check('tables_modify', $table);

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

    protected function getJS($sys_dmail_uid): string
    {
        return '
        script_ended = 0;
        function jumpToUrl(URL)	{
            window.location.href = URL;
        }
        function jumpToUrlD(URL) {
            window.location.href = URL+"&sys_dmail_uid=' . $sys_dmail_uid . '";
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
