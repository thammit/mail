<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Exception;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\CategoryRepository;
use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use MEDIAESSENZ\Mail\Events\ManipulateCsvImportDataEvent;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SysLog\Type as SystemLogType;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
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
    protected RequestInterface $request;
    protected string $refererHost;
    protected string $requestHost;

    protected ?BackendUserAuthentication $backendUserAuthentication = null;

    public function __construct(
        protected AddressRepository $addressRepository,
        protected CategoryRepository $categoryRepository,
        protected PagesRepository $pagesRepository,
        protected DataHandler $dataHandler,
        protected EventDispatcherInterface $eventDispatcher
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
        $this->request = $request;
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
     * @throws \Doctrine\DBAL\Exception
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

        $data = $this->prepareData();

        // show charset selector
        $charsets = array_unique(array_values(mb_list_encodings()));
        foreach ($charsets as $charset) {
            $data['charsets'][] = ['val' => $charset, 'text' => $charset];
        }

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
        $mapFields[] = [
            'categories',
            LanguageUtility::getLL('recipient.import.categoryMapping'),
        ];

        usort($mapFields, fn($a, $b) => strcmp(strtolower($a[1]), strtolower($b[1])));
        array_unshift($mapFields, ['noMap', '']);
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
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function startCsvImport(): array
    {
        $data = $this->prepareData([
            'hiddenMap' => [],
            'hiddenCat' => [],
            'charset' => $this->configuration['charset'],
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
        foreach ($csvData as $csvRow) {
            $tempData = [];
            $invalidEmail = false;
            foreach ($csvRow as $fieldName => $fieldValue) {
                $fieldKey = $this->configuration['map'][$fieldName];
                switch ($fieldKey) {
                    case 'noMap':
                        break;
                    case 'email':
                        if ($this->configuration['validEmail']) {
                            $email = trim($fieldValue);
                            $invalidEmail = !GeneralUtility::validEmail($email);
                            $tempData[$fieldKey] = $email;
                        }
                        break;
                    case 'categories':
                        $tempData[$fieldKey] = GeneralUtility::intExplode(',', $fieldValue, true);
                        break;
                    default:
                        $tempData[$fieldKey] = trim($fieldValue);
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

        // build array for data handler;
        $data = [];
        if ($this->configuration['updateUnique']) {
            $addresses = $this->addressRepository->findRecordByPid((int)$this->configuration['storage'], ['uid', 'email', 'name', 'mail_active'], true);

            // check addresses one by one, new or update
            foreach ($mappedCSV as $index => $dataArray) {
                $uniqueAddresses = array_filter($addresses, fn($address) => $address[$this->configuration['recordUnique']] === $dataArray[$this->configuration['recordUnique']]);
                if ($uniqueAddresses) {
                    // update existing address
                    foreach ($uniqueAddresses as $address) {
                        $data['tt_address'][$address['uid']] = $this->buildAddressData($dataArray, (bool)$address['mail_active']);
                    }
                    $resultImport['update'][] = $dataArray;
                } else {
                    // write new address
                    $data['tt_address']['NEW' . ($index + 1)] = $this->buildAddressData($dataArray, true);
                    $resultImport['new'][] = $dataArray;
                }
            }
        } else {
            // add addresses
            foreach ($mappedCSV as $index => $dataArray) {
                $data['tt_address']['NEW' . ($index + 1)] = $this->buildAddressData($dataArray, true);
                $resultImport['new'][] = $dataArray;
            }
        }

        $resultImport['invalidEmail'] = $invalidEmailCSV;
        $resultImport['double'] = $filteredCSV['double'] ?? [];

        // PSR-14 event dispatcher for further import data manipulation
        $data = $this->eventDispatcher->dispatch(new ManipulateCsvImportDataEvent($data, $this->configuration))->getData();

        // start importing
        $this->dataHandler->enableLogging = 0;
        $this->dataHandler->start($data, []);
        $this->dataHandler->process_datamap();

        return $resultImport;
    }

    protected function buildAddressData(array $data, $activateMail): array
    {
        $data['pid'] = $this->configuration['storage'];
        $data['mail_active'] = $activateMail ? 1 : 0;
        if ($this->configuration['all_html']) {
            $data['mail_html'] = $this->configuration['all_html'];
        }
        $data['categories'] = implode(',', array_unique(array_merge($data['categories'] ?? [], $this->configuration['categories'] ?? [])));

        return $data;
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
            $this->backendUserAuthentication->writelog(
                SystemLogType::FILE, 0, 2, 0,
                'Referer host "%s" and server host "%s" did not match!',
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
     * @throws \TYPO3\CMS\Core\Resource\Exception
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
                $this->backendUserAuthentication->writelog(
                    SystemLogType::FILE, 0, 2, 1,
                    'Referer host "%s" and server host "%s" did not match!',
                    [$this->refererHost, $this->requestHost]);
            } else {
                if ((new Typo3Version())->getMajorVersion() < 12) {
                    $file = GeneralUtility::_GP('tx_mail_mailmail_mailrecipient')['file'];
                } else {
                    $file = $this->request->getParsedBody()['file'] ?? $this->request->getQueryParams()['file'] ?? null;
                }
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
