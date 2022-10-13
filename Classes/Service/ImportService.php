<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Exception;
use MEDIAESSENZ\Mail\Domain\Model\Category;
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

class ImportService
{
    /**
     * The GET-Data
     * @var array
     */
    protected array $indata = [];
    protected array $params = [];

    protected int $pageId;
    protected string $httpReferer;
    protected string $requestHostOnly;

    /**
     * Init the class
     *
     * @param int $pageId
     * @param string $httpReferer
     * @param string $requestHostOnly
     * @return void
     */
    public function init(int $pageId, string $httpReferer, string $requestHostOnly): void
    {
        $this->pageId = $pageId;
        $this->httpReferer = $httpReferer;
        $this->requestHostOnly = $requestHostOnly;

        // get some importer default from pageTS
        $this->params = BackendUtility::getPagesTSconfig($this->pageId)['mod.']['web_modules.']['dmail.']['importer.'] ?? [];
    }

    /**
     * Import CSV-Data in step-by-step mode
     *
     * @return array        HTML form
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws Exception
     */
    public function csvImport(): array
    {
        $output = [
            'title' => '',
            'subtitle' => '',
            'upload' => [
                'show' => false,
                'current' => false,
                'fileInfo' => [],
                'csv' => '',
                'target' => '',
                'target_disabled' => '',
                'newFile' => '',
                'newFileUid' => 0,
            ],
            'conf' => [
                'show' => false,
                'remove_existing' => false,
                'first_fieldname' => false,
                'delimiter' => '',
                'delimiterSelected' => '',
                'encapsulation' => '',
                'valid_email' => false,
                'remove_dublette' => false,
                'update_unique' => false,
                'record_unique' => '',
                'newFile' => '',
                'newFileUid' => 0,
                'disableInput' => false,
            ],
            'mapping' => [
                'show' => false,
                'charset' => '',
                'charsetSelected' => '',
                'newFile' => '',
                'newFileUid' => 0,
                'storage' => '',
                'remove_existing' => false,
                'first_fieldname' => false,
                'delimiter' => '',
                'encapsulation' => '',
                'valid_email' => false,
                'remove_dublette' => false,
                'update_unique' => false,
                'record_unique' => '',
                'all_html' => false,
                'mapping_cats' => [],
                'show_add_cat' => false,
                'add_cat' => false,
                'error' => [],
                'table' => [],
                'fields' => [],
            ],
            'startImport' => [
                'show' => false,
                'charset' => '',
                'charsetSelected' => '',
                'newFile' => '',
                'newFileUid' => 0,
                'storage' => '',
                'remove_existing' => false,
                'first_fieldname' => false,
                'delimiter' => '',
                'encapsulation' => '',
                'valid_email' => false,
                'remove_dublette' => false,
                'update_unique' => false,
                'record_unique' => '',
                'all_html' => false,
                'hiddenMap' => [],
                'hiddenCat' => [],
                'add_cat' => false,
                'tables' => [],
            ],
        ];

        $beUser = BackendUserUtility::getBackendUser();
        $step = GeneralUtility::_GP('importStep');
        $defaultConf = [
            'remove_existing' => false,
            'first_fieldname' => false,
            'valid_email' => false,
            'remove_dublette' => false,
            'update_unique' => false,
        ];

        if ($importerConfig = GeneralUtility::_GP('CSV_IMPORT')) {
            if ($step['next'] === 'mapping') {
                foreach ($defaultConf as $key => $value) {
                    if (isset($importerConfig[$key])) {
                        $importerConfig[$key] = (bool)$importerConfig[$key];
                    }
                }
                $this->indata = $importerConfig + $defaultConf;

            } else {
                $this->indata = $importerConfig;
            }
        }

        if (empty($this->indata)) {
            $this->indata = [];
        }

        if (empty($this->params)) {
            $this->params = [];
        }

//        $currentFileInfo = BasicFileUtility::getTotalFileInfo($this->indata['newFile']);
//        $currentFileName = $currentFileInfo['file'];
//        $currentFileSize = GeneralUtility::formatSize($currentFileInfo['size']);
//        $currentFileMessage = $currentFileName . ' (' . $currentFileSize . ')';
//        $file = $this->getFileById((int)$this->indata['newFileUid']);
//        $file->getNameWithoutExtension();

        // merge it with inData, but inData has priority.
        $this->indata += $this->params;
        if (empty($this->indata['csv']) && !empty($_FILES['upload_1']['name'])) {
            $tempFile = $this->checkUpload();
            $this->indata['newFile'] = $tempFile['newFile'];
            $this->indata['newFileUid'] = $tempFile['newFileUid'];
        } elseif (!empty($this->indata['csv']) && empty($_FILES['upload_1']['name'])) {
            unset($this->indata['newFile']);
            unset($this->indata['newFileUid']);
        }
        $stepCurrent = '';
        if ($this->indata['back'] ?? false) {
            $stepCurrent = $step['back'];
        } elseif ($this->indata['next'] ?? false) {
            $stepCurrent = $step['next'];
        } elseif ($this->indata['update'] ?? false) {
            $stepCurrent = 'mapping';
        }

        if (strlen($this->indata['csv'] ?? '') > 0) {
            $this->indata['mode'] = 'csv';
            $tempFile = $this->writeTempFile($this->indata['csv'] ?? '', $this->indata['newFile'] ?? '', $this->indata['newFileUid'] ?? 0);
            $this->indata['newFile'] = $tempFile['newFile'];
            $this->indata['newFileUid'] = $tempFile['newFileUid'];
        } elseif (!empty($this->indata['newFile'])) {
            $this->indata['mode'] = 'file';
        } else {
            unset($stepCurrent);
        }

        // check if "email" is mapped
        if (isset($stepCurrent) && $stepCurrent === 'startImport') {
            $map = $this->indata['map'];
            $error = [];
            // check noMap
            $newMap = ArrayUtility::removeArrayEntryByValue(array_unique($map), 'noMap');
            if (empty($newMap)) {
                $error[] = 'noMap';
            } elseif (!in_array('email', $map)) {
                $error[] = 'email';
            }
            if (count($error)) {
                $stepCurrent = 'mapping';
            }
        }

        $out = '';
        switch ($stepCurrent ?? 'upload') {
            case 'conf':
                $output['conf']['show'] = true;
                $output['conf']['newFile'] = $this->indata['newFile'];
                $output['conf']['newFileUid'] = $this->indata['newFileUid'];

                $pagePermsClause3 = $beUser->getPagePermsClause(3);
                $pagePermsClause1 = $beUser->getPagePermsClause(1);
                // get list of sysfolder
                // TODO: maybe only subtree von this->id??

                $optStorage = [];
                $subfolders = GeneralUtility::makeInstance(PagesRepository::class)->selectSubfolders($pagePermsClause3);
                if ($subfolders && count($subfolders)) {
                    foreach ($subfolders as $subfolder) {
                        if (BackendUtility::readPageAccess($subfolder['uid'], $pagePermsClause1)) {
                            $optStorage[] = [
                                'val' => $subfolder['uid'],
                                'text' => $subfolder['title'] . ' [uid:' . $subfolder['uid'] . ']',
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

                $output['conf']['disableInput'] = (bool)($this->params['inputDisable'] ?? false);

                // show configuration
                $output['subtitle'] = LanguageUtility::getLL('mailgroup_import_header_conf');

                // get the all sysfolder
                $output['conf']['storage'] = $optStorage;
                $output['conf']['storageSelected'] = $this->indata['storage'] ?? '';

                // remove existing option
                $output['conf']['remove_existing'] = (bool)($this->indata['remove_existing'] ?? false);

                // first line in csv is to be ignored
                $output['conf']['first_fieldname'] = (bool)($this->indata['first_fieldname'] ?? false);

                // csv separator
                $output['conf']['delimiter'] = $optDelimiter;
                $output['conf']['delimiterSelected'] = $this->indata['delimiter'] ?? '';

                // csv encapsulation
                $output['conf']['encapsulation'] = $optEncap;
                $output['conf']['encapsulationSelected'] = $this->indata['encapsulation'] ?? '';

                // import only valid email
                $output['conf']['valid_email'] = (bool)($this->indata['valid_email'] ?? false);

                // only import distinct records
                $output['conf']['remove_dublette'] = (bool)($this->indata['remove_dublette'] ?? false);

                // update the record instead renaming the new one
                $output['conf']['update_unique'] = (bool)($this->indata['update_unique'] ?? false);

                // which field should be use to show uniqueness of the records
                $output['conf']['record_unique'] = $optUnique;
                $output['conf']['record_uniqueSelected'] = $this->indata['record_unique'] ?? '';

                break;

            case 'mapping':
                $output['mapping']['show'] = true;
                $output['mapping']['newFile'] = $this->indata['newFile'];
                $output['mapping']['newFileUid'] = $this->indata['newFileUid'];
                $output['mapping']['storage'] = $this->indata['storage'];
                $output['mapping']['remove_existing'] = $this->indata['remove_existing'];
                $output['mapping']['first_fieldname'] = $this->indata['first_fieldname'];
                $output['mapping']['delimiter'] = $this->indata['delimiter'];
                $output['mapping']['encapsulation'] = $this->indata['encapsulation'];
                $output['mapping']['valid_email'] = $this->indata['valid_email'];
                $output['mapping']['remove_dublette'] = $this->indata['remove_dublette'];
                $output['mapping']['update_unique'] = $this->indata['update_unique'];
                $output['mapping']['record_unique'] = $this->indata['record_unique'];
                $output['mapping']['all_html'] = (bool)($this->indata['all_html'] ?? false);
                $output['mapping']['error'] = $error ?? [];

                // show charset selector
                $cs = array_unique(array_values(mb_list_encodings()));
                $charSets = [];
                foreach ($cs as $charset) {
                    $charSets[] = ['val' => $charset, 'text' => $charset];
                }

                if (!isset($this->indata['charset'])) {
                    $this->indata['charset'] = 'ISO-8859-1';
                }
                $output['subtitle'] = LanguageUtility::getLL('mailgroup_import_mapping_charset');

                $output['mapping']['charset'] = $charSets;
                $output['mapping']['charsetSelected'] = $this->indata['charset'];

                $csv_firstRow = [];
                // show mapping form
                if ($this->indata['first_fieldname']) {
                    // read csv
                    $csvData = $this->readExampleCSV(4);
                    $csv_firstRow = $csvData[0];
                    $csvData = array_slice($csvData, 1);
                } else {
                    // read csv
                    $csvData = $this->readExampleCSV(3);
                    $fieldsAmount = count($csvData[0] ?? []);
                    for ($i = 0; $i < $fieldsAmount; $i++) {
                        $csv_firstRow[] = 'field_' . $i;
                    }
                }

                // read tt_address TCA
                $no_map = ['image', 'sys_language_uid', 'l10n_parent', 'l10n_diffsource', 't3_origuid', 'cruser_id', 'crdate', 'tstamp'];
                $ttAddressFields = array_keys($GLOBALS['TCA']['tt_address']['columns']);
                foreach ($no_map as $v) {
                    $ttAddressFields = ArrayUtility::removeArrayEntryByValue($ttAddressFields, $v);
                }
                $mapFields = [];
                foreach ($ttAddressFields as $map) {
                    $mapFields[] = [
                        $map,
                        str_replace(':', '', LanguageUtility::getLanguageService()->sL($GLOBALS['TCA']['tt_address']['columns'][$map]['label'])),
                    ];
                }
                // add 'no value'
                array_unshift($mapFields, ['noMap', LanguageUtility::getLL('mailgroup_import_mapping_mapTo')]);
                $mapFields[] = [
                    'cats',
                    LanguageUtility::getLL('mailgroup_import_mapping_categories'),
                ];
                reset($csv_firstRow);
                reset($csvData);

                $output['mapping']['fields'] = $mapFields;
                for ($i = 0; $i < (count($csv_firstRow)); $i++) {
                    // example CSV
                    $exampleLines = [];
                    for ($j = 0; $j < (count($csvData)); $j++) {
                        $exampleLines[] = $csvData[$j][$i];
                    }
                    $output['mapping']['table'][] = [
                        'mapping_description' => $csv_firstRow[$i],
                        'mapping_i' => $i,
                        'mapping_mappingSelected' => $this->indata['map'][$i] ?? '',
                        'mapping_value' => $exampleLines,
                    ];
                }

                // get categories
                $temp['value'] = BackendUtility::getPagesTSconfig($this->pageId)['TCEFORM.']['tx_mail_domain_model_group.']['categories.']['PAGE_TSCONFIG_IDLIST'] ?? null;
                if (is_numeric($temp['value'])) {
                    $categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);
                    $sysCategories = $categoryRepository->findByPid((int)$temp['value'])->toArray();
//                    $rowCat = GeneralUtility::makeInstance(SysDmailCategoryRepository::class)->selectSysDmailCategoryByPid((int)$temp['value']);
                    if (count($sysCategories) > 0) {
                        // additional options
                        if ($output['mapping']['update_unique']) {
                            $output['mapping']['show_add_cat'] = true;
                            $output['mapping']['add_cat'] = $this->indata['add_cat'] ? true : false;
                        }
                        /** @var Category $sysCategory */
                        foreach ($sysCategories as $key => $sysCategory) {
                            $output['mapping']['mapping_cats'][] = [
                                'cat' => $sysCategory->getTitle(),
                                'k' => $key,
                                'vUid' => $sysCategory->getUid(),
                                'checked' => !($this->indata['cat'][$key] != $sysCategory->getUid()),
                            ];
                        }
                    }
                }
                break;
            case 'startImport':
                $output['startImport']['show'] = true;
                $output['startImport']['charsetSelected'] = $this->indata['charset'];
                $output['startImport']['newFile'] = $this->indata['newFile'];
                $output['startImport']['newFileUid'] = $this->indata['newFileUid'];
                $output['startImport']['storage'] = $this->indata['storage'];
                $output['startImport']['remove_existing'] = $this->indata['remove_existing'];
                $output['startImport']['first_fieldname'] = $this->indata['first_fieldname'];
                $output['startImport']['delimiter'] = $this->indata['delimiter'];
                $output['startImport']['encapsulation'] = $this->indata['encapsulation'];
                $output['startImport']['valid_email'] = $this->indata['valid_email'];
                $output['startImport']['remove_dublette'] = $this->indata['remove_dublette'];
                $output['startImport']['update_unique'] = $this->indata['update_unique'];
                $output['startImport']['record_unique'] = $this->indata['record_unique'];
                $output['startImport']['all_html'] = (bool)($this->indata['all_html'] ?? false);
                $output['startImport']['add_cat'] = (bool)($this->indata['add_cat'] ?? false);
                $output['startImport']['error'] = $error ?? [];

                // starting import & show errors
                // read csv
                $csvData = $this->readCSV();
                if ($this->indata['first_fieldname']) {
                    $csvData = array_slice($csvData, 1);
                }

                // show not imported record and reasons,
                $result = $this->doImport($csvData);
                $output['subtitle'] = '';
                ViewUtility::addOkToFlashMessageQueue(LanguageUtility::getLL('mailgroup_import_done'), '');

                $resultOrder = [];
                if (!empty($this->params['resultOrder'])) {
                    $resultOrder = GeneralUtility::trimExplode(',', $this->params['resultOrder']);
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

                    $output['startImport']['tables'][] = [
                        'header' => LanguageUtility::getLL('mailgroup_import_report_' . $order),
                        'rows' => $rowsTable,
                    ];
                }

                // back button
                if (is_array($this->indata['map'])) {
                    foreach ($this->indata['map'] as $fieldNr => $fieldMapped) {
                        $output['startImport']['hiddenMap'][] = ['name' => htmlspecialchars('CSV_IMPORT[map][' . $fieldNr . ']'), 'value' => htmlspecialchars($fieldMapped)];
                    }
                }
                if (isset($this->indata['cat']) && is_array($this->indata['cat'])) {
                    foreach ($this->indata['cat'] as $k => $catUid) {
                        $output['startImport']['hiddenCat'][] = ['name' => htmlspecialchars('CSV_IMPORT[cat][' . $k . ']'), 'value' => htmlspecialchars($catUid)];
                    }
                }
                break;

            case 'upload':
            default:
                // show upload file form
                $output['subtitle'] = LanguageUtility::getLL('mailgroup_import_header_upload');

                if (isset($this->indata['mode']) && ($this->indata['mode'] === 'file')) {
                    $output['upload']['current'] = true;
                    $file = $this->getFileById((int)$this->indata['newFileUid']);
                    if ($file instanceof File) {
                        $output['upload']['fileInfo'] = [
                            'name' => $file->getName(),
                            'extension' => $file->getProperty('extension'),
                            'size' => GeneralUtility::formatSize($file->getProperty('size')),
                        ];
                    }
                }

                if ((str_contains(($currentFileInfo['file'] ?? ''), 'import') ? 1 : 0) && ($currentFileInfo['realFileext'] ?? '') === 'txt') {
                    $handleCsv = fopen($this->indata['newFile'], 'r');
                    $this->indata['csv'] = fread($handleCsv, filesize($this->indata['newFile']));
                    fclose($handleCsv);
                }

                $output['upload']['show'] = true;
                $output['upload']['csv'] = htmlspecialchars($this->indata['csv'] ?? '');
                $output['upload']['target'] = htmlspecialchars($this->userTempFolder());
                $output['upload']['target_disabled'] = GeneralUtility::_POST('importNow') ? 'disabled' : '';
                $output['upload']['newFile'] = $this->indata['newFile'] ?? null;
                $output['upload']['newFileUid'] = $this->indata['newFileUid'] ?? null;
        }

        $output['title'] = LanguageUtility::getLL('mailgroup_import') . BackendUtility::cshItem($this->cshTable ?? '', 'mailgroup_import');
        $theOutput = sprintf('%s', $out);

        /**
         *  Hook for displayImport
         *  use it to manipulate the steps in the import process
         */
        $hookObjectsArr = [];
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport']) &&
            is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
        }
        if (count($hookObjectsArr)) {
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'displayImport')) {
                    $theOutput = $hookObj->displayImport($this);
                }
            }
        }

        return ['output' => $output, 'theOutput' => $theOutput];
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
                        if ($csvData[$this->indata['record_unique']] == $cmpData[$this->indata['record_unique']]) {
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
        if ($this->indata['remove_existing']) {
            GeneralUtility::makeInstance(TtAddressRepository::class)->deleteByPid((int)$this->indata['storage']);
        }

        $mappedCSV = [];
        $invalidEmailCSV = [];
        foreach ($csvData as $dataArray) {
            $tempData = [];
            $invalidEmail = false;
            foreach ($dataArray as $kk => $fieldData) {
                if ($this->indata['map'][$kk] !== 'noMap') {
                    if (($this->indata['valid_email']) && ($this->indata['map'][$kk] === 'email')) {
                        $invalidEmail = GeneralUtility::validEmail(trim($fieldData)) === false;
                        $tempData[$this->indata['map'][$kk]] = trim($fieldData);
                    } else {
                        if ($this->indata['map'][$kk] !== 'cats') {
                            $tempData[$this->indata['map'][$kk]] = $fieldData;
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
        if ($this->indata['remove_dublette']) {
            $filteredCSV = CsvUtility::filterDuplicates($mappedCSV, $this->indata['record_unique']);
            unset($mappedCSV);
            $mappedCSV = $filteredCSV['clean'];
        }

        // array for the process_datamap();
        $data = [];
        if ($this->indata['update_unique']) {
            $user = [];
            $userID = [];

            $rows = GeneralUtility::makeInstance(TtAddressRepository::class)->findByUid((int)$this->indata['storage'], $this->indata['record_unique']);

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $user[] = $row['email'];
                    $userID[] = $row['uid'];
                }
            }

            // check user one by one, new or update
            $c = 1;
            foreach ($mappedCSV as $dataArray) {
                $foundUser = array_keys($user, $dataArray[$this->indata['record_unique']]);
                if (is_array($foundUser) && !empty($foundUser)) {
                    if (count($foundUser) == 1) {
                        $data['tt_address'][$userID[$foundUser[0]]] = $dataArray;
                        $data['tt_address'][$userID[$foundUser[0]]]['pid'] = $this->indata['storage'];
                        if ($this->indata['all_html']) {
                            $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_html'] = $this->indata['all_html'];
                        }
                        if (isset($this->indata['cat']) && is_array($this->indata['cat']) && !in_array('cats', $this->indata['map'])) {
                            if ($this->indata['add_cat']) {
                                $rows = GeneralUtility::makeInstance(SysDmailTtAddressCategoryMmRepository::class)->selectUidsByUidLocal((int)$userID[$foundUser[0]]);
                                if (is_array($rows)) {
                                    foreach ($rows as $row) {
                                        $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $row['uid_foreign'];
                                    }
                                }
                            }
                            // Add categories
                            foreach ($this->indata['cat'] as $v) {
                                $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $v;
                            }
                        }
                        $resultImport['update'][] = $dataArray;
                    } else {
                        // which one to update? all?
                        foreach ($foundUser as $kk => $_) {
                            $data['tt_address'][$userID[$foundUser[$kk]]] = $dataArray;
                            $data['tt_address'][$userID[$foundUser[$kk]]]['pid'] = $this->indata['storage'];
                        }
                        $resultImport['update'][] = $dataArray;
                    }
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
        $data['tt_address'][$id]['pid'] = $this->indata['storage'];
        if ($this->indata['all_html']) {
            $data['tt_address'][$id]['module_sys_dmail_html'] = $this->indata['all_html'];
        }
        if (isset($this->indata['cat']) && is_array($this->indata['cat']) && !in_array('cats', $this->indata['map'])) {
            foreach ($this->indata['cat'] as $k => $v) {
                $data['tt_address'][$id]['module_sys_dmail_category'][$k] = $v;
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

        if ((int)$this->indata['newFileUid'] < 1) {
            return $mydata;
        }

        $fileAbsolutePath = $this->getFileAbsolutePath((int)$this->indata['newFileUid']);

        $delimiter = $this->indata['delimiter'] ?: 'comma';
        $encaps = $this->indata['encapsulation'] ?: 'doubleQuote';
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

        if ((int)$this->indata['newFileUid'] < 1) {
            return $mydata;
        }

        $fileAbsolutePath = $this->getFileAbsolutePath((int)$this->indata['newFileUid']);

        $i = 0;
        $delimiter = $this->indata['delimiter'] ?: 'comma';
        $encaps = $this->indata['encapsulation'] ?: 'doubleQuote';
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
        $dbCharset = 'utf-8';
        if ($dbCharset != $this->indata['charset']) {
            $converter = GeneralUtility::makeInstance(CharsetConverter::class);
            foreach ($data as $k => $v) {
                $data[$k] = $converter->conv($v, strtolower($this->indata['charset']), $dbCharset);
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

        if (empty($this->indata['newFile'])) {
            // Checking referer / executing:
            $refInfo = parse_url($this->httpReferer);
            $httpHost = $this->requestHostOnly;

            if ($httpHost != $refInfo['host'] && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
                $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$refInfo['host'], $httpHost]);
            } else {
                // new file
                $file['newfile']['target'] = $this->userTempFolder();
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
        $refInfo = parse_url($this->httpReferer);
        $httpHost = $this->requestHostOnly;

        if ($httpHost != $refInfo['host'] && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
            $extendedFileUtility->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$refInfo['host'], $httpHost]);
        } else {
            $file = GeneralUtility::_GP('file');
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
    public function userTempFolder()
    {
        /** @var Folder $folder */
        $folder = BackendUserUtility::getBackendUser()->getDefaultUploadTemporaryFolder();
        return $folder->getPublicUrl();
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
