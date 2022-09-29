<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Database\QueryGenerator;
use MEDIAESSENZ\Mail\Domain\Repository\FeUsersRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtAddressRepository;
use MEDIAESSENZ\Mail\Enumeration\Action;
use MEDIAESSENZ\Mail\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility as MailCsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\RepositoryUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\CsvUtility;

class RecipientListController extends AbstractController
{
    protected string $moduleName = 'Mail_RecipientList';
    protected string $route = 'Mail_RecipientList';

    protected int $group_uid = 0;
    protected string $lCmd = '';
    protected string $csv = '';
    protected array $set = [];
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';

    protected QueryGenerator $queryGenerator;
    protected array $MOD_SETTINGS = [];

    protected int $uid = 0;
    protected string $table = '';
    protected array $indata = [];

    protected string $requestHostOnly = '';
    protected string $requestUri = '';
    protected string $httpReferer = '';
    protected array $allowedTables = ['tt_address', 'fe_users'];

    private bool $submit = false;

    protected function init(ServerRequestInterface $request): void
    {
        parent::init($request);

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestHostOnly = $normalizedParams->getRequestHostOnly();
        $this->requestUri = $normalizedParams->getRequestUri();
        $this->httpReferer = $request->getServerParams()['HTTP_REFERER'];

        $this->group_uid = (int)($parsedBody['group_uid'] ?? $queryParams['group_uid'] ?? 0);
        $this->lCmd = $parsedBody['lCmd'] ?? $queryParams['lCmd'] ?? '';
        $this->csv = $parsedBody['csv'] ?? $queryParams['csv'] ?? '';
        $this->set = is_array($parsedBody['csv'] ?? '') ? $parsedBody['csv'] : (is_array($queryParams['csv'] ?? '') ? $queryParams['csv'] : []);

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->table = (string)($parsedBody['table'] ?? $queryParams['table'] ?? '');
        $this->indata = $parsedBody['indata'] ?? $queryParams['indata'] ?? [];
        $this->submit = (bool)($parsedBody['submit'] ?? $queryParams['submit'] ?? false);

        $this->queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $this->view->assign('settings', [
            'route' => $this->route,
            'mailSysFolderUid' => $this->id,
        ]);
    }

    /**
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws DBALException
     * @throws RouteNotFoundException
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->init($request);

        if ($this->backendUserHasModuleAccess() === false) {
            $this->view->setTemplate('NoAccess');
            $this->messageQueue->addMessage(ViewUtility::getFlashMessage('If no access or if ID == zero', 'No Access', AbstractMessage::WARNING));
            $this->moduleTemplate->setContent($this->view->render());
            return new HtmlResponse($this->moduleTemplate->renderContent());
        }

        $this->view->setTemplate('RecipientList');

        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            // Direct mail module
            if (($this->pageInfo['doktype'] ?? 0) == 254) {
                // Add module data to view
                $this->view->assignMultiple($this->moduleContent());
            } else {
                if ($this->id != 0) {
                    $message = ViewUtility::getFlashMessage(LanguageUtility::getLL('dmail_noRegular'), LanguageUtility::getLL('dmail_newsletters'),
                        AbstractMessage::WARNING);
                    $this->messageQueue->addMessage($message);
                }
            }
        } else {
            $message = ViewUtility::getFlashMessage(LanguageUtility::getLL('select_folder'), LanguageUtility::getLL('header_recip'),
                AbstractMessage::WARNING);
            $this->messageQueue->addMessage($message);
        }

        // Render template and return html content
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Show the module content
     *
     * @return array The compiled content of the module.
     * @throws DBALException
     * @throws Exception
     * @throws RouteNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    protected function moduleContent(): array
    {
        $csvImportData = '';
        $data = [];
        // COMMAND:
        switch ((string)$this->getCurrentAction()) {
            case Action::RECIPIENT_LIST_USER_INFO: //@TODO ???
                $data = $this->displayUserInfo();
                $type = 1;
                break;
            case Action::RECIPIENT_LIST_MAIL_GROUP:
                $data = $this->displayMailGroup($this->compileMailGroup($this->group_uid));
                $type = 2;
                break;
            case Action::RECIPIENT_LIST_IMPORT:
                /* @var $importService ImportService */
                $importService = GeneralUtility::makeInstance(ImportService::class);
                $importService->init($this->id, $this->httpReferer, $this->requestHostOnly);
                $csvImportData = $importService->csvImport();
                $type = 3;
                break;
            default:
                $data = $this->showExistingRecipientLists();
                $type = 4;
        }

        return ['data' => $data, 'csvImportData' => $csvImportData, 'type' => $type, 'show' => true];
    }

    /**
     * Shows the existing recipient lists and shows link to create a new one or import a list
     *
     * @return array|string List of existing recipient list, link to create a new list and link to import
     * @throws DBALException
     * @throws Exception
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function showExistingRecipientLists(): array|string
    {
        $data = [
            'rows' => [],
        ];

        $rows = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupByPid($this->id,
            trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby']));

        foreach ($rows as $row) {
            $result = $this->compileMailGroup(intval($row['uid']));
            $totalRecipients = 0;
            $idLists = $result['queryInfo']['id_lists'];

            if (is_array($idLists['tt_address'] ?? false)) {
                $totalRecipients += count($idLists['tt_address']);
            }
            if (is_array($idLists['fe_users'] ?? false)) {
                $totalRecipients += count($idLists['fe_users']);
            }
            if (is_array($idLists['PLAINLIST'] ?? false)) {
                $totalRecipients += count($idLists['PLAINLIST']);
            }
            if (is_array($idLists[$this->userTable] ?? false)) {
                $totalRecipients += count($idLists[$this->userTable]);
            }

            $data['rows'][] = [
                'uid' => $row['uid'],
                'title' => $row['title'],
                'type' => htmlspecialchars(BackendUtility::getProcessedValue('sys_dmail_group', 'type', $row['type'])),
                'description' => BackendUtility::getProcessedValue('sys_dmail_group', 'description', htmlspecialchars($row['description'])),
                'count' => $totalRecipients,
            ];
        }

        $data['editOnClickLink'] = ViewUtility::getEditOnClickLink([
            'edit' => [
                'sys_dmail_group' => [
                    $this->id => 'new',
                ],
            ],
            'returnUrl' => $this->requestUri,
        ]);

        $data['sysDmailGroupIcon'] = $this->iconFactory->getIconForRecord('sys_dmail_group', [], Icon::SIZE_SMALL);

        // Import
        $data['moduleUrl'] = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'cmd' => Action::RECIPIENT_LIST_IMPORT,
            ]
        );

        return $data;
    }

    /**
     * Put all recipients uid from all table into an array
     *
     * @param int $groupUid Uid of the group
     *
     * @return array List of the uid in an array
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function compileMailGroup(int $groupUid): array
    {
        $idLists = [];
        if ($groupUid) {
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $groupUid);
            if (is_array($mailGroup) && $mailGroup['pid'] == $this->id) {
                switch ($mailGroup['type']) {
                    case RecipientGroupType::PAGES:
                        // From pages
                        // use current page if no else
                        $thePages = $mailGroup['pages'] ?: $this->id;
                        // Explode the pages
                        $pages = GeneralUtility::intExplode(',', $thePages);
                        $pageIdArray = [];
                        foreach ($pages as $pageUid) {
                            if ($pageUid > 0) {
                                $pageinfo = BackendUtility::readPageAccess($pageUid, $this->backendUserPermissions);
                                if (is_array($pageinfo)) {
                                    $info['fromPages'][] = $pageinfo;
                                    $pageIdArray[] = $pageUid;
                                    if ($mailGroup['recursive']) {
                                        $pageIdArray = array_merge($pageIdArray,
                                            BackendDataUtility::getRecursiveSelect($pageUid, $this->backendUserPermissions));
                                    }
                                }
                            }
                        }

                        // Remove any duplicates
                        $pageIdArray = array_unique($pageIdArray);
                        $pidList = implode(',', $pageIdArray);
                        $info['recursive'] = $mailGroup['recursive'];

                        // Make queries
                        if ($pidList) {
                            $whichTables = intval($mailGroup['whichtables']);
                            // tt_address
                            if ($whichTables & 1) {
                                $idLists['tt_address'] = GeneralUtility::makeInstance(TempRepository::class)
                                    ->getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_users
                            if ($whichTables & 2) {
                                $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)
                                    ->getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // user table
                            if ($this->userTable && ($whichTables & 4)) {
                                $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)
                                    ->getIdList($this->userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_groups
                            if ($whichTables & 8) {
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)
                                    ->getIdList('fe_groups', $pidList, $groupUid, $mailGroup['select_categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $idLists['fe_users']));
                            }
                        }
                        break;
                    case RecipientGroupType::CSV:
                        // List of mails
                        if ($mailGroup['csv'] == 1) {
                            $dmCsvUtility = GeneralUtility::makeInstance(MailCsvUtility::class);
                            $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroup['list']), $this->fieldList);
                        } else {
                            $recipients = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($recipients);
                        break;
                    case RecipientGroupType::STATIC:
                        // Static MM list
                        $idLists['tt_address'] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('tt_address', $groupUid);
                        $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('fe_users', $groupUid);
                        $tempGroups = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('fe_groups', $groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                        if ($this->userTable) {
                            $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList($this->userTable, $groupUid);
                        }
                        break;
                    case RecipientGroupType::QUERY:
                        // Special query list
                        $mailGroup = $this->update_SpecialQuery($mailGroup);
                        $whichTables = intval($mailGroup['whichtables']);
                        $table = '';
                        if ($whichTables & 1) {
                            $table = 'tt_address';
                        } else {
                            if ($whichTables & 2) {
                                $table = 'fe_users';
                            } else {
                                if ($this->userTable && ($whichTables & 4)) {
                                    $table = $this->userTable;
                                }
                            }
                        }
                        if ($table) {
                            $idLists[$table] = GeneralUtility::makeInstance(TempRepository::class)->getSpecialQueryIdList($table, $mailGroup,
                                $this->queryGenerator);
                        }
                        break;
                    case RecipientGroupType::OTHER:
                        $groups = array_unique(GeneralUtility::makeInstance(TempRepository::class)->getMailGroups($mailGroup['mail_groups'],
                            [$mailGroup['uid']], $this->backendUserPermissions));

                        foreach ($groups as $group) {
                            $collect = $this->compileMailGroup($group);
                            if (is_array($collect['queryInfo']['id_lists'])) {
                                $idLists = array_merge_recursive($idLists, $collect['queryInfo']['id_lists']);
                            }
                        }

                        // Make unique entries
                        if (is_array($idLists['tt_address'])) {
                            $idLists['tt_address'] = array_unique($idLists['tt_address']);
                        }
                        if (is_array($idLists['fe_users'])) {
                            $idLists['fe_users'] = array_unique($idLists['fe_users']);
                        }
                        if (is_array($idLists[$this->userTable]) && $this->userTable) {
                            $idLists[$this->userTable] = array_unique($idLists[$this->userTable]);
                        }
                        if (is_array($idLists['PLAINLIST'])) {
                            $idLists['PLAINLIST'] = RecipientUtility::removeDuplicates($idLists['PLAINLIST']);
                        }
                        break;
                    default:
                }
            }
        }
        /**
         * Hook for cmd_compileMailGroup
         * manipulate the generated id_lists
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] ?? false)) {
            $hookObjectsArr = [];

            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $mailGroup);
                }
            }

            unset($idLists);
            $idLists = $temporaryList;
        }

        return [
            'queryInfo' => ['id_lists' => $idLists],
        ];
    }

    /**
     * Display infos of the mail group
     *
     * @param array $result Array containing list of recipient uid
     *
     * @return array|string list of all recipient (HTML)
     * @throws DBALException
     * @throws Exception
     */
    protected function displayMailGroup(array $result): array|string
    {
        $totalRecipients = 0;
        $idLists = $result['queryInfo']['id_lists'];
        if (is_array($idLists['tt_address'] ?? false)) {
            $totalRecipients += count($idLists['tt_address']);
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $totalRecipients += count($idLists['fe_users']);
        }
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $totalRecipients += count($idLists['PLAINLIST']);
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $totalRecipients += count($idLists[$this->userTable]);
        }

        $group = BackendUtility::getRecord('sys_dmail_group', $this->group_uid) ?? [];

        $data = [
            'group_id' => $this->group_uid,
            'group_icon' => $this->iconFactory->getIconForRecord('sys_dmail_group', $group, Icon::SIZE_SMALL),
            'group_title' => htmlspecialchars($group['title'] ?? ''),
            'group_totalRecipients' => $totalRecipients,
//            'group_link_listall' => ($this->lCmd == '') ? GeneralUtility::linkThisScript(['lCmd' => 'listall']) : '',
            'tables' => [],
            'special' => [],
        ];

        // do the CSV export
        $csvValue = $this->csv; //'tt_address', 'fe_users', 'PLAINLIST', $this->userTable
        if ($csvValue) {
            if ($csvValue == 'PLAINLIST') {
                $this->downloadCSV($idLists['PLAINLIST']);
            } else {
                if (GeneralUtility::inList('tt_address,fe_users,' . $this->userTable, $csvValue)) {
                    if (BackendUserUtility::getBackendUser()->check('tables_select', $csvValue)) {
                        $fields = $csvValue == 'fe_users' ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList;
                        $fields .= ',tstamp';

                        $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$csvValue], $csvValue,
                            GeneralUtility::trimExplode(',', $fields, true));
                        $this->downloadCSV($rows);
                    } else {
                        $message = ViewUtility::getFlashMessage(
                            '',
                            LanguageUtility::getLL('mailgroup_table_disallowed_csv'),
                            AbstractMessage::ERROR
                        );
                        $this->messageQueue->addMessage($message);
                    }
                }
            }
        }
//        switch ($this->lCmd) {
        switch ('listall') {
            case 'listall':
                if (is_array($idLists['tt_address'] ?? false)) {
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['tt_address'], 'tt_address');

                    $data['tables']['tt_address'] = [
                        'table' => 'tt_address',
                        'recipients' => $rows,
                        'numberOfRecipients' => count($rows),
                        'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'tt_address'),
                        'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'tt_address'),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'tt_address']),
                    ];
                }
                if (is_array($idLists['fe_users'] ?? false)) {
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    $data['tables']['fe_users'] = [
                        'table' => 'fe_users',
                        'recipients' => $rows,
                        'numberOfRecipients' => count($rows),
                        'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'fe_users'),
                        'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'fe_users'),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'fe_users']),
                    ];
                }
                if (is_array($idLists['PLAINLIST'] ?? false)) {
                    $data['tables']['sys_dmail_group'] = [
                        'table' => 'sys_dmail_group',
                        'recipients' => $idLists['PLAINLIST'],
                        'numberOfRecipients' => count($idLists['PLAINLIST']),
                        'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'sys_dmail_group'),
                        'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'sys_dmail_group'),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'PLAINLIST']),
                    ];
                }
                if (is_array($idLists[$this->userTable] ?? false)) {
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
                    $data['tables'][$this->userTable] = [
                        'table' => $this->userTable,
                        'recipients' => $rows,
                        'numberOfRecipients' => count($rows),
                        'show' => BackendUserUtility::getBackendUser()->check('tables_select', $this->userTable),
                        'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', $this->userTable),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => $this->userTable]),
                    ];
                }
                break;
            default:
                if (is_array($idLists['tt_address'] ?? false) && count($idLists['tt_address'])) {
                    $data['tables']['tt_address'] = [
                        'table' => 'tt_address',
                        'title_recip' => 'mailgroup_recip_number',
                        'numberOfRecipients' => count($idLists['tt_address']),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'tt_address']),
                    ];
                }

                if (is_array($idLists['fe_users'] ?? false) && count($idLists['fe_users'])) {
                    $data['tables']['fe_users'] = [
                        'table' => 'fe_users',
                        'title_recip' => 'mailgroup_recip_number',
                        'numberOfRecipients' => count($idLists['fe_users']),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'fe_users']),
                    ];
                }

                if (is_array($idLists['PLAINLIST'] ?? false) && count($idLists['PLAINLIST'])) {
                    $data['tables']['sys_dmail_group'] = [
                        'table' => 'sys_dmail_group',
                        'title_recip' => 'mailgroup_recip_number',
                        'numberOfRecipients' => count($idLists['PLAINLIST']),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => 'PLAINLIST']),
                    ];
                }

                if (is_array($idLists[$this->userTable] ?? false) && count($idLists[$this->userTable])) {
                    $data['tables'][$this->userTable] = [
                        'table' => $this->userTable,
                        'title_table' => 'mailgroup_table_custom',
                        'title_recip' => 'mailgroup_recip_number',
                        'numberOfRecipients' => count($idLists[$this->userTable]),
                        'csvDownload' => GeneralUtility::linkThisScript(['csv' => $this->userTable]),
                    ];
                }

                if (($group['type'] ?? false) == 3) {
                    if (BackendUserUtility::getBackendUser()->check('tables_modify', 'sys_dmail_group')) {
                        $data['special'] = $this->specialQuery($group);
                    }
                }
        }
        return $data;
    }

    /**
     * Update recipient list record with a special query
     *
     * @param array $mailGroup DB records
     *
     * @return array Updated DB records
     */
    protected function update_specialQuery(array $mailGroup): array
    {
        $set = $this->set;
        $queryTable = $set['queryTable'] ?? '';
        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');

        $whichTables = intval($mailGroup['whichtables']);
        $table = '';
        if ($whichTables & 1) {
            $table = 'tt_address';
        } else {
            if ($whichTables & 2) {
                $table = 'fe_users';
            } else {
                if ($this->userTable && ($whichTables & 4)) {
                    $table = $this->userTable;
                }
            }
        }

        $this->MOD_SETTINGS['queryTable'] = $queryTable ?: $table;
        $this->MOD_SETTINGS['queryConfig'] = $queryConfig ? serialize($queryConfig) : $mailGroup['query'];
        $this->MOD_SETTINGS['search_query_smallparts'] = 1;

        if ($this->MOD_SETTINGS['queryTable'] != $table) {
            $this->MOD_SETTINGS['queryConfig'] = '';
        }

        if ($this->MOD_SETTINGS['queryTable'] != $table || $this->MOD_SETTINGS['queryConfig'] != $mailGroup['query']) {
            $whichTables = 0;
            if ($this->MOD_SETTINGS['queryTable'] == 'tt_address') {
                $whichTables = 1;
            } else {
                if ($this->MOD_SETTINGS['queryTable'] == 'fe_users') {
                    $whichTables = 2;
                } else {
                    if ($this->MOD_SETTINGS['queryTable'] == $this->userTable) {
                        $whichTables = 4;
                    }
                }
            }
            $updateFields = [
                'whichtables' => intval($whichTables),
                'query' => $this->MOD_SETTINGS['queryConfig'],
            ];

            $connection = $this->getConnection('sys_dmail_group');

            $connection->update(
                'sys_dmail_group', // table
                $updateFields,
                ['uid' => intval($mailGroup['uid'])] // where
            );
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $mailGroup['uid']);
        }
        return $mailGroup;
    }

    /**
     * Show HTML form to make special query
     *
     * @param array $mailGroup Recipient list DB record
     *
     * @return array HTML form to make a special query
     */
    protected function specialQuery(array $mailGroup): array
    {
        $special = [];

        $this->queryGenerator->init('dmail_queryConfig', $this->MOD_SETTINGS['queryTable']);

        if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
            $this->queryGenerator->queryConfig = $this->queryGenerator->cleanUpQueryConfig(unserialize($this->MOD_SETTINGS['queryConfig']));
            $this->queryGenerator->extFieldLists['queryFields'] = 'uid';
            $special['selected'] = $this->queryGenerator->getSelectQuery();
        }

        $this->queryGenerator->setFormName('dmailform');
        $this->queryGenerator->noWrap = '';
        $this->queryGenerator->allowedTables = $this->allowedTables;

        $special['selectTables'] = $this->queryGenerator->makeSelectorTable($this->MOD_SETTINGS, 'table,query');

        return $special;
    }

    /**
     * Send csv values as download by sending appropriate HTML header
     *
     * @param array $idArr Values to be put into csv
     *
     * @return void Sent HML header for a file download
     */
    protected function downloadCSV(array $idArr)
    {
        // https://api.typo3.org/master/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_utility_1_1_csv_utility.html
        $lines = [];
        if (count($idArr)) {
            reset($idArr);
            $lines[] = CsvUtility::csvValues(array_keys(current($idArr)));

            reset($idArr);
            foreach ($idArr as $rec) {
                $lines[] = CsvUtility::csvValues($rec);
            }
        }

        $filename = 'mail_recipients_' . date('dmy-Hi') . '.csv';
        $mimeType = 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename=' . $filename);
        echo implode(CR . LF, $lines);
        exit;
    }

    /**
     * Shows user's info and categories
     *
     * @return array|string HTML showing user's info and the categories
     * @throws DBALException
     * @throws Exception
     */
    protected function displayUserInfo(): array|string
    {
        if (!in_array($this->table, ['tt_address', 'fe_users'])) {
            return [];
        }

        if ($this->submit && count($this->indata) === 0) {
            $this->indata['html'] = false;
        }

        if (count($this->indata)) {
            $data = [];
            if (is_array($this->indata['categories'] ?? false)) {
                reset($this->indata['categories']);
                foreach ($this->indata['categories'] as $recValues) {
                    reset($recValues);
                    $enabled = [];
                    foreach ($recValues as $k => $b) {
                        if ($b) {
                            $enabled[] = $k;
                        }
                    }
                    $data[$this->table][$this->uid]['module_sys_dmail_category'] = implode(',', $enabled);
                }
            }
            $data[$this->table][$this->uid]['module_sys_dmail_html'] = (bool)$this->indata['html'];

            $dataHandler = $this->getDataHandler();
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();
        }

        $rows = [];
        switch ($this->table) {
            case 'tt_address':
                $rows = GeneralUtility::makeInstance(TtAddressRepository::class)->findByUidAndPermission($this->uid, $this->backendUserPermissions);
                break;
            case 'fe_users':
                $rows = GeneralUtility::makeInstance(FeUsersRepository::class)->findByUidAndPermissions($this->uid, $this->backendUserPermissions);
                break;
            default:
                // do nothing
        }

        $row = $rows[0] ?? [];
        $data = [];

        if (is_array($row) && count($row)) {
            $mmTable = $GLOBALS['TCA'][$this->table]['columns']['module_sys_dmail_category']['config']['MM'];

            $queryBuilder = $this->getQueryBuilder($mmTable);
            $resCat = $queryBuilder
                ->select('uid_foreign')
                ->from($mmTable)
                ->where($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($row['uid'])))
                ->execute()
                ->fetchAllAssociative();

            $categoriesArray = [];
            foreach ($resCat as $rowCat) {
                $categoriesArray[] = $rowCat['uid_foreign'];
            }

            $categories = implode(',', $categoriesArray);

//            $editOnClickLink = ViewUtility::getEditOnClickLink([
//                'edit' => [
//                    $this->table => [
//                        $row['uid'] => 'edit',
//                    ],
//                ],
//                'returnUrl' => $this->requestUri,
//            ]);

            $data = [
//                'icon' => $this->iconFactory->getIconForRecord($this->table, $row)->render(),
//                'iconActionsOpen' => $this->getIconActionsOpen(),
                'name' => $row['name'],
                'email' => $row['email'],
                'uid' => $row['uid'],
//                'editOnClickLink' => $editOnClickLink,
                'categories' => [],
                'table' => $this->table,
                'thisID' => $this->uid,
                'cmd' => (string)$this->getCurrentAction(),
                'html' => (bool)$row['module_sys_dmail_html'],
            ];

            $tableRowCategories = RepositoryUtility::makeCategories($this->table, $row, $this->sysLanguageUid);
            reset($tableRowCategories);
            foreach ($tableRowCategories as $pKey => $pVal) {
                $data['categories'][] = [
                    'pkey' => $pKey,
                    'pVal' => $pVal,
                    'checked' => GeneralUtility::inList($categories, $pKey),
                ];
            }
        }
        return $data;
    }
}
