<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use JetBrains\PhpStorm\NoReturn;
use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\Category;
use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
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
     *
     * @param string $str String in csv-format
     * @param string $separator Separator
     * @param array $fieldList
     * @return array parsed CSV values
     */
    public static function rearrangeCsvValues(string $str, string $separator = ',', array $fieldList = []): array
    {
        $data = [];

        $fh = tmpfile();
        fwrite($fh, trim($str));
        fseek($fh, 0);
        $separator = $separator === 'tab' ? "\t" : $separator;
        $lines = [];
        while ($line = fgetcsv($fh, 1000, $separator)) {
            $lines[] = $line;
        }
        fclose($fh);

        if (count($lines) > 0) {

            // Analyse if first line contain field names.
            // It is necessary that a value is either
            // 1) found in the fields list
            // 2) is empty (value omitted then)
            // 3) starts with "user_".
            // In addition, fields may be prepended with "[code]".
            // This is used if the incoming value is true
            // in that case '+[value]' adds that number to the field value (accumulation) and '=[value]' overrides any existing value in the field

            $firstRow = $lines[0];
            $hasFieldNames = true;
            $fieldOrder = [];

            foreach ($firstRow as $value) {
                $fieldName = '';
                $probe = preg_split('|[\[\]]|', strtolower($value));
                if (is_array($probe)) {
                    [$fieldName, $fieldConfiguration] = count($probe) === 2 ? $probe : [$probe[0], ''];
                }
                $fieldName = strtolower(trim($fieldName ?? ''));
                $fieldOrder[] = [$fieldName, trim($fieldConfiguration ?? '')];
                if ($fieldName && !str_starts_with($fieldName, 'user_') && !in_array($fieldName, $fieldList)) {
                    $hasFieldNames = false;
                    break;
                }
            }

            if ($hasFieldNames) {
                // if the first line contain field names remove first line for next steps
                array_shift($lines);
            } else {
                $fieldOrder = [
                    ['name'],
                    ['email'],
                    ['salutation'],
                ];
            }

            $rowNumber = 0;
            foreach ($lines as $line) {
                // Must be a line with content.
                // This sorts out entries with one key which is empty. Those are empty lines.
                if (count($line) > 1 || $line[0]) {
                    // Traverse fieldOrder and map values over
                    foreach ($fieldOrder as $column => $fieldConfiguration) {
                        if ($fieldConfiguration[0]) {
                            if ($fieldConfiguration[1] ?? false) {
                                // If column exist and is not empty
                                if (array_key_exists($column, $line) && trim($line[$column])) {
                                    if (str_starts_with($fieldConfiguration[1], '=')) {
                                        $data[$rowNumber][$fieldConfiguration[0]] = trim(substr($fieldConfiguration[1], 1));
                                    } else if (str_starts_with($fieldConfiguration[1], '+')) {
                                        $data[$rowNumber][$fieldConfiguration[0]] .= substr($fieldConfiguration[1], 1);
                                    }
                                }
                            } else {
                                $data[$rowNumber][$fieldConfiguration[0]] = trim($line[$column]);
                            }
                        }
                    }
                    $rowNumber++;
                }
            }
        }
        return $data;
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
                foreach ($cmpCsv as $kk =>$cmpData) {
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
        if ($data['addresses']) {
            /** @var Address $address */
            foreach ($data['addresses'] as $address) {
                $emails[] = ['uid' => $address->getUid(), 'email' => $address->getEmail(), 'name' => $address->getName()];
            }
        }
        if ($data['frontendUsers']) {
            /** @var FrontendUser $frontendUser */
            foreach ($data['frontendUsers'] as $frontendUser) {
                $emails[] = ['uid' => $frontendUser->getUid(), 'email' => $frontendUser->getEmail(), 'name' => $frontendUser->getName()];
            }
        }
        if ($data['plainList']) {
            foreach ($data['plainList'] as $value) {
                $emails[] = ['uid' => '-', 'email' => $value, 'name' => ''];
            }
        }

        return self::downloadCSV($emails, $filenamePrefix);
    }

}
