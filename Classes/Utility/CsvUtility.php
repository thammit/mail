<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CsvUtility
{
    /**
     * Parse CSV lines into array form
     * @param Group $group
     * @return array
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getRecipientDataFromCSVGroup(Group $group): array
    {
        $separator = $group->getCsvSeparatorString();
        $csvRawData = $group->getCsvData();
        $allRecipientFields = RecipientUtility::getAllRecipientFields();
        $firstLineOfCsvContainFieldNames = $group->isCsvFieldNames();

        $fh = tmpfile();
        fwrite($fh, trim($csvRawData));
        fseek($fh, 0);
        $csvDataArray = [];
        while ($line = fgetcsv($fh, 1000, $separator)) {
            $csvDataArray[] = $line;
        }
        fclose($fh);

        if (!$csvDataArray || ($firstLineOfCsvContainFieldNames && count($csvDataArray) === 1)) {
            return [];
        }

        if ($firstLineOfCsvContainFieldNames) {
            // It is necessary that either
            //   - contains field "name" and "email"
            //   - found in the fields list
            //   - is empty (value omitted then)
            //   - fields may be prepended with "[code]".
            //     This is used if the incoming value is true
            //     in that case '+[value]' adds that number to the field value (accumulation) and '=[value]' overrides any existing value in the field
            $fieldNames = $csvDataArray[0];

            if (!in_array('name', $fieldNames, true) || !in_array('email', $fieldNames, true)) {
                return [];
            }

            $fieldOrder = [];

            foreach ($fieldNames as $column => $fieldName) {
                $fieldName = trim($fieldName ?? '');
                $probe = preg_split('|[\[\]]|', $fieldName);
                if (is_array($probe)) {
                    [$fieldName, $fieldConfiguration] = count($probe) === 2 ? $probe : [$probe[0], ''];
                }
                $fieldName = strtolower(trim($fieldName ?? ''));
                if (!$fieldName || !in_array($fieldName, $allRecipientFields, true)) {
                    continue;
                }
                $fieldOrder[$column] = [$fieldName, trim($fieldConfiguration ?? '')];
            }

            // remove first line of csv data
            array_shift($csvDataArray);

        } else {
            $fieldOrder = [
                ['name'],
                ['email'],
                ['salutation'],
            ];
        }

        $data = [];

        foreach ($csvDataArray as $row => $csvRow) {
            // Must be a line with content.
            // This sorts out entries with one key which is empty. Those are empty lines.
            if (count($csvRow) > 1 || $csvRow[0]) {
                // Traverse fieldOrder and map values over
                foreach ($fieldOrder as $column => $fieldConfiguration) {
                    if ($fieldConfiguration[0]) {
                        if ($fieldConfiguration[1] ?? false) {
                            // If column exist and is not empty
                            if (array_key_exists($column, $csvRow) && trim($csvRow[$column])) {
                                if (str_starts_with($fieldConfiguration[1], '=')) {
                                    $data[$row][$fieldConfiguration[0]] = trim(substr($fieldConfiguration[1], 1));
                                } else {
                                    if (str_starts_with($fieldConfiguration[1], '+')) {
                                        $data[$row][$fieldConfiguration[0]] .= substr($fieldConfiguration[1], 1);
                                    }
                                }
                            }
                        } else {
                            $data[$row][$fieldConfiguration[0]] = trim($csvRow[$column] ?? '');
                        }
                    }
                }

                $email = $data[$row]['email'] ?? '';
                if (!$email || !GeneralUtility::validEmail($email)) {
                    // remove entries with invalid email
                    unset($data[$row]);
                }
            }
        }

        return array_values($data);
    }

    /**
     * Filter duplicates from input csv data
     *
     * @param array $mappedCsv Mapped csv
     *
     * @return array Filtered csv and double csv
     */
    public static function filterDuplicates(array $mappedCsv, string $uniqueKey): array
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
                        if ($csvData[$uniqueKey] == $cmpData[$uniqueKey]) {
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
     * Convert charset if necessary
     *
     * @param array $data Contains values to convert
     * @param string $fromCharset
     * @param string $toCharset
     *
     * @return array array of charset-converted values
     * @see \TYPO3\CMS\Core\Charset\CharsetConverter::conv[]
     */
    public static function convertCharsetOfDataArray(array $data, string $fromCharset, string $toCharset = 'utf-8'): array
    {
        // todo check database charset
        if ($toCharset !== strtolower($fromCharset)) {
            $converter = GeneralUtility::makeInstance(CharsetConverter::class);
            $data = array_map(
                fn($row): array => array_map(
                    fn($column): string => $converter->conv($column, strtolower($fromCharset), strtolower($toCharset)),
                    $row
                ),
                $data
            );
        }
        return $data;
    }

    /**
     * Send csv values as download by sending appropriate HTML header
     *
     * @param array $rows Values to be put into csv
     * @param string $filenamePrefix
     * @return ResponseInterface file download
     */
    public static function downloadCSV(array $rows, string $filenamePrefix = 'mail_recipients'): ResponseInterface
    {
        $lines = [];
        if (count($rows)) {
            reset($rows);
            $lines[] = \TYPO3\CMS\Core\Utility\CsvUtility::csvValues(array_keys(current($rows)));

            foreach ($rows as $rec) {
                $lines[] = \TYPO3\CMS\Core\Utility\CsvUtility::csvValues($rec);
            }
        }

        $responseFactory = GeneralUtility::makeInstance(ResponseFactory::class);
        $streamFactory = GeneralUtility::makeInstance(StreamFactory::class);

        return $responseFactory->createResponse()
            ->withAddedHeader('Content-Type', 'application/octet-stream')
            ->withAddedHeader('Content-Disposition', 'attachment; filename=' . $filenamePrefix . '_' . date('dmy-Hi') . '.csv')
            ->withBody($streamFactory->createStream(implode(CR . LF, $lines)));
    }

    /**
     * @param array $data
     * @param string $filenamePrefix
     * @return ResponseInterface
     */
    public static function csvDownloadRecipientsCSV(array $data, string $filenamePrefix): ResponseInterface
    {
        $emails = [];
        $recipientSourceConfiguration = $data['configuration'] ?? null;
        if ($recipientSourceConfiguration instanceof RecipientSourceConfigurationDTO &&
        $data['recipients'] ?? false) {
            foreach ($data['recipients'] as $recipient) {
                $emails[] = ['uid' => $recipient['uid'] ?? '-', 'email' => $recipient['email'], 'name' => $recipient['name'] ?? ''];
            }
        }

        return self::downloadCSV($emails, $filenamePrefix);
    }

}
