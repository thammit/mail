<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Exception;
use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\CategoryRepository;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysCategoryRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailTtAddressCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtAddressRepository;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;

class ImportService
{
    /**
     * The GET-Data
     * @var array
     */
    protected array $configuration = [];
    protected array $pageTsConfiguration = [];

    protected int $pageId;
    protected string $refererHost;
    protected string $requestHost;

    public function __construct(
        protected AddressRepository $addressRepository,
        protected CategoryRepository $categoryRepository
    )
    {
    }

    /**
     * Init the class
     *
     * @param int $pageId
     * @param Request $request
     * @param array $configuration
     * @return void
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    public function init(int $pageId, Request $request, array $configuration = []): void
    {
        $this->pageId = $pageId;
//        $this->pageId = (int)($request->getQueryParams()['id'] ?? $request->getAttribute('site')->getRootPageId() ?? 0);
        $this->requestHost = $request->getUri()->getHost();
        $this->refererHost = parse_url($request->getServerParams()['HTTP_REFERER'], PHP_URL_HOST);

        // get some importer default from pageTS
        $this->pageTsConfiguration = BackendUtility::getPagesTSconfig($this->pageId)['mod.']['web_modules.']['mail.']['importer.'] ?? [];
        $this->mergeConfigurations($configuration);
    }

    /**
     * @param array $configuration
     * @return void
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws Exception
     */
    protected function mergeConfigurations(array $configuration = []): void
    {
        $this->configuration = $configuration + $this->pageTsConfiguration;

        if (empty($this->configuration['csv']) && !empty($_FILES['upload_1']['name'])) {
            $tempFile = $this->checkUpload();
            $this->configuration['newFile'] = $tempFile['newFile'];
            $this->configuration['newFileUid'] = $tempFile['newFileUid'];
        } elseif (!empty($this->configuration['csv']) && empty($_FILES['upload_1']['name'])) {
            unset($this->configuration['newFile']);
            unset($this->configuration['newFileUid']);
        }

        if (strlen($this->configuration['csv'] ?? '') > 0) {
            $this->configuration['mode'] = 'csv';
            $tempFile = $this->writeTempFile($this->configuration['csv'] ?? '', $this->configuration['newFile'] ?? '', $this->configuration['newFileUid'] ?? 0);
            $this->configuration['newFile'] = $tempFile['newFile'];
            $this->configuration['newFileUid'] = $tempFile['newFileUid'];
        } elseif (!empty($this->configuration['newFile'])) {
            $this->configuration['mode'] = 'file';
        }
    }

    public function getCsvImportUploadData(): array
    {
        $data['csv'] = htmlspecialchars($this->configuration['csv'] ?? '');
        $data['target'] = htmlspecialchars($this->importFolder());
        $data['newFile'] = $this->configuration['newFile'] ?? null;
        $data['newFileUid'] = $this->configuration['newFileUid'] ?? null;

        return $data;
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws DBALException
     */
    public function getCsvImportConfigurationData(): array
    {
        $data['newFile'] = $this->configuration['newFile'];
        $data['newFileUid'] = $this->configuration['newFileUid'];

        $beUser = BackendUserUtility::getBackendUser();
        $pagePermsClause3 = $beUser->getPagePermsClause(3);
        $pagePermsClause1 = $beUser->getPagePermsClause(1);
        // get list of sysfolder
        // TODO: maybe only subtree von this->id??

        $optStorage = [];
        $subFolders = GeneralUtility::makeInstance(PagesRepository::class)->selectSubfolders($pagePermsClause3);
        if ($subFolders && count($subFolders)) {
            foreach ($subFolders as $subFolder) {
                if (BackendUtility::readPageAccess($subFolder['uid'], $pagePermsClause1)) {
                    $optStorage[] = [
                        'val' => $subFolder['uid'],
                        'text' => $subFolder['title'] . ' [uid:' . $subFolder['uid'] . ']',
                    ];
                }
            }
        }

        $optDelimiter = [
            ['val' => 'comma', 'text' => LanguageUtility::getLL('mailgroup_import_separator_comma')],
            ['val' => 'semicolon', 'text' => LanguageUtility::getLL('mailgroup_import_separator_semicolon')],
            ['val' => 'colon', 'text' => LanguageUtility::getLL('mailgroup_import_separator_colon')],
            ['val' => 'tab', 'text' => LanguageUtility::getLL('mailgroup_import_separator_tab')],
        ];

        $optEncap = [
            ['val' => 'doubleQuote', 'text' => ' " '],
            ['val' => 'singleQuote', 'text' => " ' "],
        ];

        // TODO: make it variable?
        $optUnique = [
            ['val' => 'email', 'text' => 'email'],
            ['val' => 'name', 'text' => 'name'],
        ];

        $data['disableInput'] = (bool)($this->pageTsConfiguration['inputDisable'] ?? false);

        // get the all sysfolder
        $data['storage'] = $optStorage;
        $data['storageSelected'] = $this->configuration['storage'] ?? '';

        // remove existing option
        $data['remove_existing'] = (bool)($this->configuration['remove_existing'] ?? false);

        // first line in csv is to be ignored
        $data['first_fieldname'] = (bool)($this->configuration['first_fieldname'] ?? false);

        // csv separator
        $data['delimiter'] = $optDelimiter;
        $data['delimiterSelected'] = $this->configuration['delimiter'] ?? '';

        // csv encapsulation
        $data['encapsulation'] = $optEncap;
        $data['encapsulationSelected'] = $this->configuration['encapsulation'] ?? '';

        // import only valid email
        $data['valid_email'] = (bool)($this->configuration['valid_email'] ?? false);

        // only import distinct records
        $data['remove_dublette'] = (bool)($this->configuration['remove_dublette'] ?? false);

        // update the record instead renaming the new one
        $data['update_unique'] = (bool)($this->configuration['update_unique'] ?? false);

        // which field should be use to show uniqueness of the records
        $data['record_unique'] = $optUnique;
        $data['record_uniqueSelected'] = $this->configuration['record_unique'] ?? '';

        return $data;
    }

    public function getCsvImportMappingData(): array
    {
        $defaultConf = [
            'remove_existing' => false,
            'first_fieldname' => false,
            'valid_email' => false,
            'remove_dublette' => false,
            'update_unique' => false,
        ];
        foreach ($defaultConf as $key => $value) {
            if (!isset($this->configuration[$key])) {
                $this->configuration[$key] = $value;
            }
        }

        $data = [
            'mapping_cats' => [],
            'showAddCategories' => false,
            'addCategories' => false,
            'table' => [],
        ];
        $data['newFile'] = $this->configuration['newFile'];
        $data['newFileUid'] = $this->configuration['newFileUid'];
        $data['storage'] = $this->configuration['storage'];
        $data['remove_existing'] = $this->configuration['remove_existing'];
        $data['first_fieldname'] = $this->configuration['first_fieldname'];
        $data['delimiter'] = $this->configuration['delimiter'];
        $data['encapsulation'] = $this->configuration['encapsulation'];
        $data['valid_email'] = $this->configuration['valid_email'];
        $data['remove_dublette'] = $this->configuration['remove_dublette'];
        $data['update_unique'] = $this->configuration['update_unique'];
        $data['record_unique'] = $this->configuration['record_unique'];
        $data['all_html'] = (bool)($this->configuration['all_html'] ?? false);
        $data['error'] = $error ?? [];

        // show charset selector
        $cs = array_unique(array_values(mb_list_encodings()));
        $charSets = [];
        foreach ($cs as $charset) {
            $charSets[] = ['val' => $charset, 'text' => $charset];
        }

        if (!isset($this->configuration['charset'])) {
            $this->configuration['charset'] = 'ISO-8859-1';
        }

        $data['charset'] = $charSets;
        $data['charsetSelected'] = $this->configuration['charset'];

        $columnNames = [];
        // show mapping form
        if ($this->configuration['first_fieldname']) {
            // read csv
            $csvData = $this->readExampleCSV(4);
            $columnNames = $csvData[0];
            $csvData = array_slice($csvData, 1);
        } else {
            // read csv
            $csvData = $this->readExampleCSV(3);
            $fieldsAmount = count($csvData[0] ?? []);
            for ($i = 0; $i < $fieldsAmount; $i++) {
                $columnNames[] = 'field_' . $i;
            }
        }

        // read tt_address TCA
        $removeColumns = ['image', 'sys_language_uid', 'l10n_parent', 'l10n_diffsource', 't3_origuid', 'cruser_id', 'crdate', 'tstamp'];
        $ttAddressColumns = array_keys($GLOBALS['TCA']['tt_address']['columns']);
        foreach ($removeColumns as $column) {
            $ttAddressColumns = ArrayUtility::removeArrayEntryByValue($ttAddressColumns, $column);
        }
        $mapFields = [];
        foreach ($ttAddressColumns as $column) {
            $mapFields[] = [
                $column,
                str_replace(':', '', LanguageUtility::getLanguageService()->sL($GLOBALS['TCA']['tt_address']['columns'][$column]['label'])),
            ];
        }
        // add 'no value'
        array_unshift($mapFields, ['noMap', LanguageUtility::getLL('mailgroup_import_mapping_mapTo')]);
        $mapFields[] = [
            'cats',
            LanguageUtility::getLL('mailgroup_import_mapping_categories'),
        ];
        reset($columnNames);
        reset($csvData);

        $data['fields'] = $mapFields;
        for ($i = 0; $i < (count($columnNames)); $i++) {
            // example CSV
            $exampleLines = [];
            for ($j = 0; $j < (count($csvData)); $j++) {
                $exampleLines[] = $csvData[$j][$i];
            }
            $data['table'][] = [
                'mapping_description' => $columnNames[$i],
                'mapping_i' => $i,
                'mapping_mappingSelected' => $this->configuration['map'][$i] ?? '',
                'mapping_value' => $exampleLines,
            ];
        }

        // get categories
        $categoryPid = BackendUtility::getPagesTSconfig($this->pageId)['TCEFORM.']['tx_mail_domain_model_group.']['categories.']['PAGE_TSCONFIG_IDLIST'] ?? null;
        if (is_numeric($categoryPid)) {
            $categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);
            $sysCategories = $categoryRepository->findByPid((int)$categoryPid);
            if (count($sysCategories) > 0) {
                // additional options
                if ($data['update_unique']) {
                    $data['showAddCategories'] = true;
                    $data['addCategories'] = (bool)$this->configuration['addCategories'];
                }
                /** @var Category $sysCategory */
                foreach ($sysCategories as $sysCategory) {
                    $data['categories'][] = [
                        'title' => $sysCategory->getTitle(),
                        'uid' => $sysCategory->getUid(),
                        'checked' => (int)($this->configuration['cat'][$sysCategory->getUid()] ?? 0) === $sysCategory->getUid(),
                    ];
                }
            }
        }

        return $data;
    }

    public function validateMapping(): bool
    {
        $map = $this->configuration['map'];
        $newMap = ArrayUtility::removeArrayEntryByValue(array_unique($map), 'noMap');
        if (empty($newMap) || !in_array('email', $map)) {
            return false;
        }

        return true;
    }

    public function startCsvImport(): array
    {
        $data = [
            'charset' => '',
            'hiddenMap' => [],
            'hiddenCat' => [],
            'tables' => [],
            'charsetSelected' => $this->configuration['charset'],
            'newFile' =>  $this->configuration['newFile'],
            'newFileUid' =>  $this->configuration['newFileUid'],
            'storage' =>  $this->configuration['storage'],
            'remove_existing' =>  $this->configuration['remove_existing'],
            'first_fieldname' =>  $this->configuration['first_fieldname'],
            'delimiter' =>  $this->configuration['delimiter'],
            'encapsulation' =>  $this->configuration['encapsulation'],
            'valid_email' =>  $this->configuration['valid_email'],
            'remove_dublette' =>  $this->configuration['remove_dublette'],
            'update_unique' =>  $this->configuration['update_unique'],
            'record_unique' =>  $this->configuration['record_unique'],
            'all_html' =>  (bool)($this->configuration['all_html'] ?? false),
            'addCategories' =>  (bool)($this->configuration['addCategories'] ?? false),
            'error' =>  $error ?? [],
        ];

        // starting import & show errors
        // read csv
        $csvData = $this->readCSV();
        if ($this->configuration['first_fieldname']) {
            // remove field names row
            $csvData = array_slice($csvData, 1);
        }

        // show not imported record and reasons,
        $result = $this->doImport($csvData);
        ViewUtility::addOkToFlashMessageQueue(LanguageUtility::getLL('mailgroup_import_done'), '');

        $resultOrder = [];
        if (!empty($this->pageTsConfiguration['resultOrder'])) {
            $resultOrder = GeneralUtility::trimExplode(',', $this->pageTsConfiguration['resultOrder']);
        }

        $defaultOrder = ['new', 'update', 'invalid_email', 'double'];
        $diffOrder = array_diff($defaultOrder, $resultOrder);
        $endOrder = array_merge($resultOrder, $diffOrder);

        foreach ($endOrder as $order) {
            $rowsTable = [];
            if (isset($result[$order]) && is_array($result[$order])) {
                foreach ($result[$order] as $v) {
                    $mapKeys = array_keys($v);
                    $rowsTable[] = [
                        'val' => $v[$mapKeys[0]],
                        'email' => $v['email'],
                    ];
                }
            }

            $data['tables'][] = [
                'header' => LanguageUtility::getLL('mailgroup_import_report_' . $order),
                'rows' => $rowsTable,
            ];
        }

        return $data;
    }

    /*****
     * function for importing tt_address
     *****/

    /**
     * Filter doublette from input csv data
     *
     * @param array $mappedCsv Mapped csv
     *
     * @return array Filtered csv and double csv
     */
    public function filterCSV(array $mappedCsv, string $uniqueKey): array
    {
        $cmpCsv = $mappedCsv;
        $remove = [];
        $filtered = [];
        $double = [];

        foreach ($mappedCsv as $k => $csvData) {
            if (!in_array($k, $remove)) {
                $found = 0;
                foreach ($cmpCsv as $kk => $cmpData) {
                    if ($k != $kk) {
                        if ($csvData[$this->configuration['record_unique']] == $cmpData[$this->configuration['record_unique']]) {
                            $double[] = $mappedCsv[$kk];
                            if (!$found) {
                                $filtered[] = $csvData;
                            }
                            $remove[] = $kk;
                            $found = 1;
                        }
                    }
                }
                if (!$found) {
                    $filtered[] = $csvData;
                }
            }
        }
        $csv['clean'] = $filtered;
        $csv['double'] = $double;

        return $csv;
    }

    /**
     * Start importing users
     *
     * @param array $csvData The csv raw data
     *
     * @return array Array containing doublette, updated and invalid-email records
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function doImport(array $csvData): array
    {
        $resultImport = [];
        $filteredCSV = [];

        // empty table if flag is set
        if ($this->configuration['remove_existing']) {
            $this->addressRepository->deleteByPid((int)$this->configuration['storage']);
        }

        $mappedCSV = [];
        $invalidEmailCSV = [];
        foreach ($csvData as $dataArray) {
            $tempData = [];
            $invalidEmail = false;
            foreach ($dataArray as $kk => $fieldData) {
                if ($this->configuration['map'][$kk] !== 'noMap') {
                    if (($this->configuration['valid_email']) && ($this->configuration['map'][$kk] === 'email')) {
                        $invalidEmail = GeneralUtility::validEmail(trim($fieldData)) === false;
                        $tempData[$this->configuration['map'][$kk]] = trim($fieldData);
                    } else {
                        if ($this->configuration['map'][$kk] !== 'cats') {
                            $tempData[$this->configuration['map'][$kk]] = $fieldData;
                        } else {
                            $tempCats = explode(',', $fieldData);
                            foreach ($tempCats as $catC => $tempCat) {
                                $tempData['module_sys_dmail_category'][$catC] = $tempCat;
                            }
                        }
                    }
                }
            }
            if ($invalidEmail) {
                $invalidEmailCSV[] = $tempData;
            } else {
                $mappedCSV[] = $tempData;
            }
        }

        // remove duplicates from csv data
        if ($this->configuration['remove_dublette']) {
            $filteredCSV = CsvUtility::filterDuplicates($mappedCSV, $this->configuration['record_unique']);
            unset($mappedCSV);
            $mappedCSV = $filteredCSV['clean'];
        }

        // array for the process_datamap();
        $data = [];
        if ($this->configuration['update_unique']) {
            $user = [];
            $userID = [];

            $addresses = $this->addressRepository->findByPid((int)$this->configuration['storage']);

            if ($addresses->count() > 0) {
                /** @var Address $address */
                foreach ($addresses as $address) {
                    $user[] = $address->getEmail();
                    $userID[] = $address->getUid();
                }
            }

            // check user one by one, new or update
            $c = 1;
            foreach ($mappedCSV as $dataArray) {
                $foundUser = array_keys($user, $dataArray[$this->configuration['record_unique']]);
                if (!empty($foundUser)) {
                    if (count($foundUser) === 1) {
                        $firstUser = $foundUser[0];
                        $firstUserUid = $userID[$firstUser];
                        $data['tt_address'][$firstUserUid] = $dataArray;
                        $data['tt_address'][$firstUserUid]['pid'] = $this->configuration['storage'];
                        if ($this->configuration['all_html']) {
                            $data['tt_address'][$firstUserUid]['accepts_html'] = $this->configuration['all_html'];
                        }
                        if (isset($this->configuration['cat']) && is_array($this->configuration['cat']) && !in_array('cats', $this->configuration['map'])) {
                            if ($this->configuration['addCategories']) {
                                // todo use CategoryRepository
//                                $this->categoryRepository->findByUidLocal
                                $rows = GeneralUtility::makeInstance(SysDmailTtAddressCategoryMmRepository::class)->selectUidsByUidLocal($firstUserUid);
                                if (is_array($rows)) {
                                    foreach ($rows as $row) {
                                        $data['tt_address'][$firstUserUid]['categories'][] = $row['uid_foreign'];
                                    }
                                }
                            }
                            // Add categories
                            foreach ($this->configuration['categories'] as $category) {
                                $data['tt_address'][$firstUserUid]['categories'][] = $category;
                            }
                        }
                    } else {
                        // which one to update? all?
                        foreach ($foundUser as $kk => $_) {
                            $data['tt_address'][$userID[$foundUser[$kk]]] = $dataArray;
                            $data['tt_address'][$userID[$foundUser[$kk]]]['pid'] = $this->configuration['storage'];
                        }
                    }
                    $resultImport['update'][] = $dataArray;
                } else {
                    // write new user
                    $this->addDataArray($data, 'NEW' . $c, $dataArray);
                    $resultImport['new'][] = $dataArray;
                    // counter
                    $c++;
                }
            }
        } else {
            // no update, import all
            $c = 1;
            foreach ($mappedCSV as $dataArray) {
                $this->addDataArray($data, 'NEW' . $c, $dataArray);
                $resultImport['new'][] = $dataArray;
                $c++;
            }
        }

        $resultImport['invalid_email'] = $invalidEmailCSV;
        $resultImport['double'] = is_array($filteredCSV['double']) ? $filteredCSV['double'] : [];

        // start importing
        /* @var $dataHandler DataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->stripslashes_values = 0;
        $dataHandler->enableLogging = 0;
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        /**
         * Hook for doImport Mail
         * will be called every time a record is inserted
         */
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'])) {
            $hookObjectsArr = [];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }

            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'doImport')) {
                    $hookObj->doImport($this, $data, $c);
                }
            }
        }

        return $resultImport;
    }

    /**
     * Prepare insert array for the TCE
     *
     * @param array $data Array for the TCE
     * @param string $id Record ID
     * @param array $dataArray The data to be imported
     *
     * @return void
     */
    public function addDataArray(array &$data, string $id, array $dataArray): void
    {
        $data['tt_address'][$id] = $dataArray;
        $data['tt_address'][$id]['pid'] = $this->configuration['storage'];
        if ($this->configuration['all_html']) {
            $data['tt_address'][$id]['accepts_html'] = 1;
        }
        if (isset($this->configuration['categories']) && is_array($this->configuration['categories']) && !in_array('cats', $this->configuration['map'])) {
            foreach ($this->configuration['categories'] as $k => $v) {
                if ($v) {
                    $data['tt_address'][$id]['categories'][$k] = (int)$v;
                }
            }
        }
    }

    /**
     * Read in the given CSV file. The function is used during the final file import.
     * Removes first the first data row if the CSV has fieldnames.
     *
     * @return    array        file content in array
     */
    public function readCSV(): array
    {
        $mydata = [];

        if ((int)$this->configuration['newFileUid'] < 1) {
            return $mydata;
        }

        $fileAbsolutePath = $this->getFileAbsolutePath((int)$this->configuration['newFileUid']);

        $delimiter = $this->configuration['delimiter'] ?: 'comma';
        $encaps = $this->configuration['encapsulation'] ?: 'doubleQuote';
        $delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
        $delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
        $delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
        $delimiter = ($delimiter === 'tab') ? "\t" : $delimiter;
        $encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
        $encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;

        ini_set('auto_detect_line_endings', true);
        $handle = fopen($fileAbsolutePath, 'r');
        if ($handle === false) {
            return $mydata;
        }

        while (($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== false) {
            // remove empty line in csv
            if ((count($data) >= 1)) {
                $mydata[] = $data;
            }
        }
        fclose($handle);
        $mydata = $this->convCharset($mydata);
        ini_set('auto_detect_line_endings', false);
        return $mydata;
    }

    /**
     * Read in the given CSV file. Only showed a couple of the CSV values as example
     * Removes first the first data row if the CSV has fieldnames.
     *
     * @param int $records Number of example values
     *
     * @return    array File content in array
     */
    public function readExampleCSV(int $records = 3): array
    {
        $mydata = [];

        if ((int)$this->configuration['newFileUid'] < 1) {
            return $mydata;
        }

        $fileAbsolutePath = $this->getFileAbsolutePath((int)$this->configuration['newFileUid']);

        $i = 0;
        $delimiter = $this->configuration['delimiter'] ?: 'comma';
        $encaps = $this->configuration['encapsulation'] ?: 'doubleQuote';
        $delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
        $delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
        $delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
        $delimiter = ($delimiter === 'tab') ? "\t" : $delimiter;
        $encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
        $encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;

        ini_set('auto_detect_line_endings', true);
        $handle = fopen($fileAbsolutePath, 'r');
        if ($handle === false) {
            return $mydata;
        }

        while ((($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== false)) {
            // remove empty line in csv
            if ((count($data) >= 1)) {
                $mydata[] = $data;
                $i++;
                if ($i >= $records) {
                    break;
                }
            }
        }
        fclose($handle);
        reset($mydata);
        $mydata = $this->convCharset($mydata);
        ini_set('auto_detect_line_endings', false);
        return $mydata;
    }

    /**
     * Convert charset if necessary
     *
     * @param array $data Contains values to convert
     *
     * @return    array    array of charset-converted values
     * @see \TYPO3\CMS\Core\Charset\CharsetConverter::conv[]
     */
    public function convCharset(array $data): array
    {
        // todo check database charset
        $dbCharset = 'utf-8';
        if ($dbCharset !== strtolower($this->configuration['charset'])) {
            $converter = GeneralUtility::makeInstance(CharsetConverter::class);
            foreach ($data as $k => $v) {
                $data[$k] = $converter->conv($v, strtolower($this->configuration['charset']), $dbCharset);
            }
        }
        return $data;
    }

    /**
     * Write CSV Data to a temporary file and will be used for the import
     *
     * @param string $csv
     * @param string $newFile
     * @param int $newFileUid
     * @return array        path and uid of the temp file
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    public function writeTempFile(string $csv, string $newFile, int $newFileUid): array
    {
        $newfile = ['newFile' => '', 'newFileUid' => 0];

        $userPermissions = BackendUserUtility::getBackendUser()->getFilePermissions();
        // Initializing:
        /* @var $extendedFileUtility ExtendedFileUtility */
        $extendedFileUtility = GeneralUtility::makeInstance(ExtendedFileUtility::class);
        $extendedFileUtility->setActionPermissions($userPermissions);
        //https://docs.typo3.org/c/typo3/cms-core/11.5/en-us/Changelog/7.4/Deprecation-63603-ExtendedFileUtilitydontCheckForUniqueIsDeprecated.html
        $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::REPLACE);

        if (empty($this->configuration['newFile'])) {
            // Checking referer / executing:
            if ($this->requestHost != $this->refererHost && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
                $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$this->refererHost, $this->requestHost]);
            } else {
                // new file
                $file['newfile']['target'] = $this->importFolder();
                $file['newfile']['data'] = 'import_' . $this->getTimestampFromAspect() . '.txt';
                $extendedFileUtility->start($file);
                $newfileObj = $extendedFileUtility->func_newfile($file['newfile']);
                if (is_object($newfileObj)) {
                    $storageConfig = $newfileObj->getStorage()->getConfiguration();
                    $newfile['newFile'] = $storageConfig['basePath'] . ltrim($newfileObj->getIdentifier(), '/');
                    $newfile['newFileUid'] = $newfileObj->getUid();
                }
            }
        } else {
            $newfile = ['newFile' => $newFile, 'newFileUid' => $newFileUid];
        }

        if ($newfile['newFile']) {
            $csvFile = [
                'data' => $csv,
                'target' => $newfile['newFile'],
            ];
            $write = $extendedFileUtility->func_edit($csvFile);
        }
        return $newfile;
    }

    /**
     * Checks if a file has been uploaded and returns the complete physical fileinfo if so.
     *
     * @return    array    \TYPO3\CMS\Core\Resource\File    the complete physical file name, including path info.
     * @throws Exception
     */
    public function checkUpload(): array
    {
        $newfile = ['newFile' => '', 'newFileUid' => 0];

        // Initializing:
        /* @var $extendedFileUtility ExtendedFileUtility */
        $extendedFileUtility = GeneralUtility::makeInstance(ExtendedFileUtility::class);
        $extendedFileUtility->setActionPermissions();
        $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::REPLACE);

        // Checking referer / executing:
        if ($this->requestHost != $this->refererHost && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
            $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$this->refererHost, $this->requestHost]);
        } else {
            $file = GeneralUtility::_GP('tx_mail_mail_mailrecipient')['file'];
            $extendedFileUtility->start($file);
            $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::cast(DuplicationBehavior::REPLACE));
            $tempFile = $extendedFileUtility->func_upload($file['upload']['1']);

            if (is_object($tempFile[0])) {
                $storageConfig = $tempFile[0]->getStorage()->getConfiguration();
                $newfile = [
                    'newFile' => rtrim($storageConfig['basePath'], '/') . '/' . ltrim($tempFile[0]->getIdentifier(), '/'),
                    'newFileUid' => $tempFile[0]->getUid(),
                ];
            }
        }

        return $newfile;
    }

    /**
     *
     * @param int $fileUid
     * @return File|bool
     */
    private function getFileById(int $fileUid): File|bool
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        try {
            return $resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException $e) {

        }
        return false;
    }

    /**
     *
     * @param int $fileUid
     * @return string
     */
    private function getFileAbsolutePath(int $fileUid): string
    {
        $file = $this->getFileById($fileUid);
        if (!is_object($file)) {
            return '';
        }
        return Environment::getPublicPath() . '/' . str_replace('//', '/', $file->getStorage()->getConfiguration()['basePath'] . $file->getProperty('identifier'));
    }

    /**
     * Returns first temporary folder of the user account
     *
     * @return    string Absolute path to first "_temp_" folder of the current user, otherwise blank.
     */
    public function importFolder(): string
    {
        /** @var Folder $folder */
        $folder = BackendUserUtility::getBackendUser()->getDefaultUploadTemporaryFolder();
        return $folder->getPublicUrl() . '/importexport';
    }

    /**
     *
     * @return int
     * @throws AspectNotFoundException
     */
    private function getTimestampFromAspect(): int
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return $context->getPropertyFromAspect('date', 'timestamp');
    }
}
