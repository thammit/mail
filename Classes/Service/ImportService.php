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
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

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

    protected ?BackendUserAuthentication $backendUserAuthentication = null;

    public function __construct(
        protected AddressRepository $addressRepository,
        protected CategoryRepository $categoryRepository,
        protected PagesRepository $pagesRepository,
        protected DataHandler $dataHandler
    ) {
        $this->backendUserAuthentication = $GLOBALS['BE_USER'];
    }

    /**
     * Init the class
     *
     * @param int $pageId
     * @param Request $request
     * @param array $configuration
     * @return void
     * @throws Exception
     */
    public function init(int $pageId, Request $request, array $configuration = []): void
    {
        $this->pageId = $pageId;
        $this->requestHost = $request->getUri()->getHost();
        $this->refererHost = parse_url($request->getServerParams()['HTTP_REFERER'], PHP_URL_HOST);

        // get some importer default from pageTS
        $this->pageTsConfiguration = BackendUtility::getPagesTSconfig($this->pageId)['mod.']['web_modules.']['mail.']['importer.'] ?? [];

        $configurationFromSession = [];
        if ($this->backendUserAuthentication->getSessionData('mailRecipientCsvImportConfiguration')) {
            $configurationFromSession = $this->backendUserAuthentication->getSessionData('mailRecipientCsvImportConfiguration');
        }

        if ($configuration) {
            $this->configuration = $configuration + $configurationFromSession + $this->pageTsConfiguration;
        } else {
            if ($configurationFromSession) {
                $this->configuration = $configurationFromSession + $this->pageTsConfiguration;
            } else {
                $this->configuration = $this->pageTsConfiguration;
            }
        }
        $this->storeConfigurationInSession();
    }

    public function getCsvImportUploadData(): array
    {
        return [
            'csv' => $this->configuration['csv'] ?? '',
            'target' => $this->importFolder()
        ];
    }

    /**
     * @return bool return true if import/upload was successfully
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws Exception
     */

    /**
     * @return bool return true if import/upload was successfully
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws Exception
     */
    public function importCsv(): bool
    {
        if ($this->configuration['csv'] && ($this->configuration['csv'] ?? '') !== '') {
            $this->createCsvFile($this->configuration['csv']);
        }
        return (bool)($this->configuration['newFileUid'] ?? false);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws DBALException
     */
    public function getCsvImportConfigurationData(): array
    {
        $data['newFile'] = $this->configuration['newFile'] ?? '';
        $data['newFileUid'] = $this->configuration['newFileUid'] ?? 0;

        $beUser = BackendUserUtility::getBackendUser();
        $pagePermsClause3 = $beUser->getPagePermsClause(Permission::PAGE_SHOW | Permission::PAGE_EDIT);
        $pagePermsClause1 = $beUser->getPagePermsClause(Permission::PAGE_SHOW);
        // get list of sysfolder
        // TODO: maybe only subtree von this->id??

        $optStorage = [];
        $subFolders = $this->pagesRepository->selectSubfolders($pagePermsClause3);
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
            ['val' => 'comma', 'text' => LanguageUtility::getLL('recipient.import.separator.comma')],
            ['val' => 'semicolon', 'text' => LanguageUtility::getLL('recipient.import.separator.semicolon')],
            ['val' => 'colon', 'text' => LanguageUtility::getLL('recipient.import.separator.colon')],
            ['val' => 'tab', 'text' => LanguageUtility::getLL('recipient.import.separator.tab')],
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
        $data['removeExisting'] = (bool)($this->configuration['removeExisting'] ?? false);

        // first line in csv is to be ignored
        $data['firstFieldname'] = (bool)($this->configuration['firstFieldname'] ?? false);

        // csv separator
        $data['delimiter'] = $optDelimiter;
        $data['delimiterSelected'] = $this->configuration['delimiter'] ?? '';

        // csv encapsulation
        $data['encapsulation'] = $optEncap;
        $data['encapsulationSelected'] = $this->configuration['encapsulation'] ?? '';

        // import only valid email
        $data['validEmail'] = (bool)($this->configuration['validEmail'] ?? false);

        // only import distinct records
        $data['removeDublette'] = (bool)($this->configuration['removeDublette'] ?? false);

        // update the record instead renaming the new one
        $data['updateUnique'] = (bool)($this->configuration['updateUnique'] ?? false);

        // which field should be used to show uniqueness of the records
        $data['recordUnique'] = $optUnique;
        $data['recordUniqueSelected'] = $this->configuration['recordUnique'] ?? '';

        return $data;
    }

    public function getCsvImportMappingData(): array
    {
        $this->configuration['removeExisting'] ??= false;
        $this->configuration['firstFieldname'] ??= false;
        $this->configuration['validEmail'] ??= false;
        $this->configuration['removeDublette'] ??= false;
        $this->configuration['updateUnique'] ??= false;
        $this->configuration['charset'] ??= 'UTF-8';

        $data = $this->prepareData([
            'mapping_cats' => [],
            'showAddAllCategories' => false,
            'addAllCategories' => false,
        ]);

        // show charset selector
        $cs = array_unique(array_values(mb_list_encodings()));
        $charSets = [];
        foreach ($cs as $charset) {
            $charSets[] = ['val' => $charset, 'text' => $charset];
        }

        $data['charsets'] = $charSets;
        $data['charset'] = $this->configuration['charset'] ?? 'UTF-8';

        $columnNames = [];
        // show mapping form
        if ($this->configuration['firstFieldname']) {
            // read csv
            $csvData = $this->readCSV(4);
            $columnNames = $csvData[0];
            $csvData = array_slice($csvData, 1);
        } else {
            // read csv
            $csvData = $this->readCSV(3);
            $fieldsAmount = count($csvData[0] ?? []);
            for ($i = 0; $i < $fieldsAmount; $i++) {
                $columnNames[] = 'field_' . $i;
            }
        }

        // read tt_address TCA
        $removeColumns = [
            'image',
            'sys_language_uid',
            'l10n_parent',
            'l10n_diffsource',
            't3_origuid',
            'cruser_id',
            'crdate',
            'tstamp'
        ];
        $ttAddressColumns = array_keys($GLOBALS['TCA']['tt_address']['columns']);
        foreach ($removeColumns as $column) {
            $ttAddressColumns = ArrayUtility::removeArrayEntryByValue($ttAddressColumns, $column);
        }
        $mapFields = [];
        foreach ($ttAddressColumns as $column) {
            $mapFields[] = [
                $column,
                str_replace(':', '',
                    LanguageUtility::getLanguageService()->sL($GLOBALS['TCA']['tt_address']['columns'][$column]['label'])),
            ];
        }
        // add 'no value'
        array_unshift($mapFields, ['noMap', LanguageUtility::getLL('recipient.import.mapping.mapTo')]);
        $mapFields[] = [
            'cats',
            LanguageUtility::getLL('recipient.import.categoryMapping'),
        ];
        reset($columnNames);
        reset($csvData);

        $data['fields'] = $mapFields;
        $numberOfColumns = count($columnNames);
        for ($i = 0; $i < $numberOfColumns; $i++) {
            // example CSV
            $exampleLines = [];
            foreach ($csvData as $jValue) {
                $exampleLines[] = $jValue[$i];
            }
            $data['table'][] = [
                'mapping_description' => $columnNames[$i],
                'mapping_i' => $i,
                'mapping_mappingSelected' => $this->configuration['map'][$i] ?? '',
                'mapping_value' => $exampleLines,
            ];
        }

        // get categories from TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.startingPoints
        $configTreeStartingPoints = BackendUtility::getPagesTSconfig($this->pageId)['TCEFORM.']['tx_mail_domain_model_group.']['categories.']['config.']['treeConfig.']['startingPoints'] ?? false;
        if ($configTreeStartingPoints !== false) {
            $configTreeStartingPointsArray = GeneralUtility::intExplode(',', $configTreeStartingPoints, true);
            foreach ($configTreeStartingPointsArray as $startingPoint) {
                $sysCategories = $this->categoryRepository->findByParent($startingPoint);
                $data = $this->addCategoryData($sysCategories, $data);
            }
        } else {
            // if no startingPoints set use all categories
            $sysCategories = $this->categoryRepository->findAll();
            $data = $this->addCategoryData($sysCategories, $data);
        }

        return $data;
    }

    public function validateMapping(): bool
    {
        $map = $this->configuration['map'];
        $newMap = ArrayUtility::removeArrayEntryByValue(array_unique($map), 'noMap');
        return !(empty($newMap) || !in_array('email', $map, true));
    }

    /**
     * @return array
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function startCsvImport(): array
    {
        $data = $this->prepareData([
            'hiddenMap' => [],
            'hiddenCat' => [],
            'charset' => $this->configuration['charset'],
            'addAllCategories' => (bool)($this->configuration['addAllCategories'] ?? false),
        ]);

        // starting import & show errors
        // read csv
        $csvData = $this->readCSV();
        if ($this->configuration['firstFieldname']) {
            // remove field names row
            $csvData = array_slice($csvData, 1);
        }

        // show not imported record and reasons,
        $result = $this->doImport($csvData);
        ViewUtility::addFlashMessageSuccess(LanguageUtility::getLL('recipient.import.notification.done.message'));

        $resultOrder = [];
        if (!empty($this->pageTsConfiguration['resultOrder'])) {
            $resultOrder = GeneralUtility::trimExplode(',', $this->pageTsConfiguration['resultOrder']);
        }

        $defaultOrder = ['new', 'update', 'invalidEmail', 'double'];
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
                'header' => LanguageUtility::getLL('recipient.import.result.' . $order),
                'rows' => $rowsTable,
            ];
        }

        return $data;
    }

    /**
     * @param array $additionalData
     * @return array
     */
    public function prepareData(array $additionalData = []): array
    {
        return array_merge([
            'table' => [],
            'newFile' => $this->configuration['newFile'],
            'newFileUid' => $this->configuration['newFileUid'],
            'storage' => $this->configuration['storage'],
            'removeExisting' => $this->configuration['removeExisting'],
            'firstFieldname' => $this->configuration['firstFieldname'],
            'delimiter' => $this->configuration['delimiter'],
            'encapsulation' => $this->configuration['encapsulation'],
            'validEmail' => $this->configuration['validEmail'],
            'removeDublette' => $this->configuration['removeDublette'],
            'updateUnique' => $this->configuration['updateUnique'],
            'recordUnique' => $this->configuration['recordUnique'],
            'all_html' => (bool)($this->configuration['all_html'] ?? false),
            'error' => [],
        ], $additionalData);
    }

    /**
     * Start importing users
     *
     * @param array $csvData The csv raw data
     *
     * @return array Array containing double, updated and invalid-email records
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function doImport(array $csvData): array
    {
        $resultImport = [];
        $filteredCSV = [];

        // empty table if flag is set
        if ($this->configuration['removeExisting']) {
            $this->addressRepository->deleteRecordByPid((int)$this->configuration['storage']);
        }

        $mappedCSV = [];
        $invalidEmailCSV = [];
        foreach ($csvData as $dataArray) {
            $tempData = [];
            $invalidEmail = false;
            foreach ($dataArray as $kk => $fieldData) {
                if ($this->configuration['map'][$kk] !== 'noMap') {
                    if (($this->configuration['validEmail']) && ($this->configuration['map'][$kk] === 'email')) {
                        $invalidEmail = GeneralUtility::validEmail(trim($fieldData)) === false;
                        $tempData[$this->configuration['map'][$kk]] = trim($fieldData);
                    } elseif ($this->configuration['map'][$kk] !== 'cats') {
                        $tempData[$this->configuration['map'][$kk]] = $fieldData;
                    } else {
                        $tempCats = explode(',', $fieldData);
                        foreach ($tempCats as $catC => $tempCat) {
                            $tempData['categories'][$catC] = $tempCat;
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
        if ($this->configuration['removeDublette']) {
            $filteredCSV = CsvUtility::filterDuplicates($mappedCSV, $this->configuration['recordUnique']);
            unset($mappedCSV);
            $mappedCSV = $filteredCSV['clean'];
        }

        // array for the process_datamap();
        $data = [];
        if ($this->configuration['updateUnique']) {
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
                $uniqueUserIds = array_keys($user, $dataArray[$this->configuration['recordUnique']]);
                if (!empty($uniqueUserIds)) {
                    if (count($uniqueUserIds) === 1) {
                        $firstUser = $uniqueUserIds[0];
                        $firstUserUid = $userID[$firstUser];
                        $data['tt_address'][$firstUserUid] = $dataArray;
                        $data['tt_address'][$firstUserUid]['pid'] = $this->configuration['storage'];
                        $data['tt_address'][$firstUserUid]['mail_active'] = 1;
                        if ($this->configuration['all_html']) {
                            $data['tt_address'][$firstUserUid]['mail_html'] = $this->configuration['all_html'];
                        }
                        if (isset($this->configuration['cat']) && is_array($this->configuration['cat']) && !in_array('cats',
                                $this->configuration['map'], true)) {
                            if ($this->configuration['addAllCategories']) {
                                $configTreeStartingPoints = BackendUtility::getPagesTSconfig($this->pageId)['TCEFORM.']['tx_mail_domain_model_group.']['categories.']['config.']['treeConfig.']['startingPoints'] ?? false;
                                if ($configTreeStartingPoints !== false) {
                                    $configTreeStartingPointsArray = GeneralUtility::intExplode(',',
                                        $configTreeStartingPoints, true);
                                    foreach ($configTreeStartingPointsArray as $startingPoint) {
                                        $sysCategories = $this->categoryRepository->findByParent($startingPoint);
                                        if ($sysCategories->count() > 0) {
                                            /** @var Category $sysCategory */
                                            foreach ($sysCategories as $sysCategory) {
                                                $data['tt_address'][$firstUserUid]['categories'][] = $sysCategory->getUid();
                                            }
                                        }
                                    }
                                } else {
                                    $sysCategories = $this->categoryRepository->findAll();
                                    if ($sysCategories->count() > 0) {
                                        /** @var Category $sysCategory */
                                        foreach ($sysCategories as $sysCategory) {
                                            $data['tt_address'][$firstUserUid]['categories'][] = $sysCategory->getUid();
                                        }
                                    }
                                }
                            } else {
                                // Add selected categories
                                foreach ($this->configuration['categories'] as $category) {
                                    $data['tt_address'][$firstUserUid]['categories'][] = $category;
                                }
                            }
                        }
                    } else {
                        // which one to update? all?
                        foreach ($uniqueUserIds as $userId) {
                            $data['tt_address'][$userID[$userId]] = $dataArray;
                            $data['tt_address'][$userID[$userId]]['pid'] = $this->configuration['storage'];
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

        $resultImport['invalidEmail'] = $invalidEmailCSV;
        $resultImport['double'] = is_array($filteredCSV['double'] ?? false) ? $filteredCSV['double'] : [];

        // start importing
        $this->dataHandler->enableLogging = 0;
        $this->dataHandler->start($data, []);
        $this->dataHandler->process_datamap();

        // Todo Add PSR-14 event dispatcher to manipulate imported address after import

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
        $data['tt_address'][$id]['mail_active'] = 1;
        if ($this->configuration['all_html']) {
            $data['tt_address'][$id]['mail_html'] = 1;
        }
        if (isset($this->configuration['categories']) && is_array($this->configuration['categories']) && !in_array('cats',
                $this->configuration['map'], true)) {
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
    public function readCSV($max = 0): array
    {
        $data = [];

        if ((int)$this->configuration['newFileUid'] < 1) {
            return $data;
        }

        $fileAbsolutePath = $this->getFileAbsolutePath((int)$this->configuration['newFileUid']);

        $delimiter = $this->configuration['delimiter'] ?: 'comma';
        $encapsulation = $this->configuration['encapsulation'] ?: 'doubleQuote';
        $delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
        $delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
        $delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
        $delimiter = ($delimiter === 'tab') ? "\t" : $delimiter;
        $encapsulation = ($encapsulation === 'singleQuote') ? "'" : $encapsulation;
        $encapsulation = ($encapsulation === 'doubleQuote') ? '"' : $encapsulation;

        @ini_set('auto_detect_line_endings', true);
        $handle = fopen($fileAbsolutePath, 'rb');
        if ($handle === false) {
            return $data;
        }

        while (($dataRow = fgetcsv($handle, 10000, $delimiter, $encapsulation)) !== false) {
            // remove empty line in csv
            if (count($dataRow) >= 1) {
                $data[] = $dataRow;
                if ($max !== 0 && count($data) >= $max) {
                    break;
                }
            }
        }
        fclose($handle);
        $data = CsvUtility::convertCharset($data, $this->configuration['charset']);
        ini_set('auto_detect_line_endings', false);
        return $data;
    }

    /**
     * Write CSV Data to a temporary file used by the import
     *
     * @param string $csv
     * @return void
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    public function createCsvFile(string $csv): void
    {
        $userPermissions = BackendUserUtility::getBackendUser()->getFilePermissions();
        // Initializing:
        /* @var $extendedFileUtility ExtendedFileUtility */
        $extendedFileUtility = GeneralUtility::makeInstance(ExtendedFileUtility::class);
        $extendedFileUtility->setActionPermissions($userPermissions);
        $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::REPLACE);

        // Checking referer / executing:
        if ($this->requestHost !== $this->refererHost && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
            $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!',
                [$this->refererHost, $this->requestHost]);
        } else {
            // new file
            $fileConfiguration['newfile']['target'] = $this->importFolder();
            $fileConfiguration['newfile']['data'] = 'import_' . $this->getTimestampFromAspect() . '.csv';
            $extendedFileUtility->start($fileConfiguration);
            $newFile = $extendedFileUtility->func_newfile($fileConfiguration['newfile']);
            if ($newFile instanceof File) {
                $storageConfig = $newFile->getStorage()->getConfiguration();
                $newFilePath = $storageConfig['basePath'] . ltrim($newFile->getIdentifier(), '/');
                $newFileUid = $newFile->getUid();
                if ($newFilePath) {
                    $csvFile = [
                        'data' => $csv,
                        'target' => $newFilePath,
                    ];
                    // write csv data to new created file
                    if ($extendedFileUtility->func_edit($csvFile)) {
                        $this->configuration['newFile'] = $newFilePath;
                        $this->configuration['newFileUid'] = $newFileUid;
                        $this->storeConfigurationInSession();
                        return;
                    }
                }
            }
        }
        $this->configuration['newFile'] = '';
        $this->configuration['newFileUid'] = 0;
    }

    /**
     * Upload file and set $this->configuration['newFile'] and $this->configuration['newFileUid']
     *
     * @return bool
     * @throws Exception
     */
    public function uploadCsv(): bool
    {
        unset($this->configuration['newFile'], $this->configuration['newFileUid']);

        if ($_FILES['upload_1']['name']) {
            $this->configuration['newFile'] = '';
            $this->configuration['newFileUid'] = 0;

            /* @var $extendedFileUtility ExtendedFileUtility */
            $extendedFileUtility = GeneralUtility::makeInstance(ExtendedFileUtility::class);
            $extendedFileUtility->setActionPermissions();
            $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::REPLACE);

            if ($this->requestHost !== $this->refererHost && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
                $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!',
                    [$this->refererHost, $this->requestHost]);
            } else {
                $file = GeneralUtility::_GP('tx_mail_mailmail_mailrecipient')['file'];
                $extendedFileUtility->start($file);
                $extendedFileUtility->setExistingFilesConflictMode(DuplicationBehavior::cast(DuplicationBehavior::REPLACE));
                $tempFile = $extendedFileUtility->func_upload($file['upload']['1']);

                if ($tempFile[0] instanceof File) {
                    $storageConfig = $tempFile[0]->getStorage()->getConfiguration();
                    $this->configuration['newFile'] = rtrim($storageConfig['basePath'],
                            '/') . '/' . ltrim($tempFile[0]->getIdentifier(), '/');
                    $this->configuration['newFileUid'] = $tempFile[0]->getUid();
                    $this->storeConfigurationInSession();
                }
            }
        }
        return (bool)($this->configuration['newFileUid'] ?? false);
    }

    /**
     * @param int $fileUid
     * @return File|bool
     */
    private function getFileById(int $fileUid): File|bool
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        try {
            return $resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {

        }
        return false;
    }

    /**
     * @param int $fileUid
     * @return string
     */
    private function getFileAbsolutePath(int $fileUid): string
    {
        $file = $this->getFileById($fileUid);
        if (!is_object($file)) {
            return '';
        }
        return Environment::getPublicPath() . '/' . str_replace('//', '/',
                $file->getStorage()->getConfiguration()['basePath'] . $file->getProperty('identifier'));
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
        return $folder->getPublicUrl() . 'importexport';
    }

    /**
     * @return int
     * @throws AspectNotFoundException
     */
    private function getTimestampFromAspect(): int
    {
        return GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
    }

    /**
     * @param QueryResultInterface $sysCategories
     * @param array $data
     * @return array
     */
    protected function addCategoryData(QueryResultInterface $sysCategories, array $data): array
    {
        if ($sysCategories->count() > 0) {
            if ($data['updateUnique']) {
                $data['showAddAllCategories'] = true;
                $data['addAllCategories'] = (bool)($this->configuration['addAllCategories'] ?? false);
            }
            /** @var Category $sysCategory */
            foreach ($sysCategories as $sysCategory) {
                $data['categories'][] = [
                    'uid' => $sysCategory->getUid(),
                    'title' => $sysCategory->getTitle(),
                    'checked' => (int)($this->configuration['categories'][$sysCategory->getUid()] ?? 0) === $sysCategory->getUid(),
                ];
            }
        }
        return $data;
    }

    protected function storeConfigurationInSession(): void
    {
        $this->backendUserAuthentication->setAndSaveSessionData('mailRecipientCsvImportConfiguration',
            $this->configuration);
    }
}
