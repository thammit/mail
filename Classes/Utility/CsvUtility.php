<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class CsvUtility
{
    public static function parseCsvRawData(string $csvRawData, string $csvSeparator = ',', string $csvEnclosure = '"'): array
    {
        $fh = tmpfile();
        fwrite($fh, trim($csvRawData));
        fseek($fh, 0);
        $csvDataArray = [];
        while ($line = fgetcsv($fh, 1000, $csvSeparator, $csvEnclosure)) {
            $csvDataArray[] = $line;
        }
        fclose($fh);

        return $csvDataArray;
    }

    /**
     * Parse CSV lines into array form
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getRecipientsFromCsvData(array $csvDataArray, bool $firstLineOfCsvContainFieldNames, bool $allFields = true): array
    {
        if (!$csvDataArray || ($firstLineOfCsvContainFieldNames && count($csvDataArray) === 1)) {
            return [];
        }

        if ($firstLineOfCsvContainFieldNames) {
            // It is necessary that either
            //   - contains field "name" and "email"
            //   - found in the fields list
            //   - is empty (value omitted then)
            //   - fields may be prepended with "[code]".
            $fieldNames = $csvDataArray[0];

            if (!in_array('email', $fieldNames, true)) {
                // first line of csv doesn't contain email field
                return [];
            }

            $allRecipientFields = $allFields ? [] : RecipientUtility::getAllRecipientFields();

            // build up field order array

            $fieldOrder = [];

            foreach ($fieldNames as $column => $fieldName) {
                $fieldName = trim($fieldName ?? '');
                $probe = preg_split('|[\[\]]|', $fieldName);
                if (is_array($probe)) {
                    // the field name contain simple "code" wrapped in []
                    // [+value] adds (if both values can be interpreted as integers) or concat the given value to the field value
                    // [=value] overrides any existing value in the field, if it is not empty
                    // Attention: even the value "0" is not empty!
                    // Here are some examples:
                    // if the field name is "gender[+1]" and the field value is "0" it will become 1 (0 + 1) in the final data, because both values can be interpreted as integer
                    // if the field name is "gender[+m]" and the field value is "0" it will become "0m" (PHP string concat: "0" . "m")
                    // if the field name is "gender[=m]" and the field value is "" (empty) or "0" it will become ""
                    // if the field name is "gender[=m]" and the field value has any other value than "" or "0" it will become "m"
                    [$fieldName, $fieldModification] = count($probe) === 2 ? $probe : [$probe[0], ''];
                }
                $fieldName = strtolower(trim($fieldName ?? ''));
                if (!$fieldName || ($allRecipientFields && !in_array($fieldName, $allRecipientFields, true))) {
                    continue;
                }
                $fieldOrder[$column] = [$fieldName, trim($fieldModification ?? '')];
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
                    [$fieldName, $fieldModification] = array_pad($fieldConfiguration, 2, null);
                    if ($fieldName) {
                        $fieldValue = trim($csvRow[$column] ?? '');
                        if ($fieldValue !== '' && $fieldModification) {
                            $modificationOperator = substr($fieldModification, 0, 1);
                            $modificationValue = substr($fieldModification, 1);
                            if ($fieldValue && $modificationOperator === '=') {
                                $fieldValue = $modificationValue;
                            } else {
                                if ($modificationOperator === '+') {
                                    if (MathUtility::canBeInterpretedAsInteger($fieldValue) && MathUtility::canBeInterpretedAsInteger($modificationValue)) {
                                        $fieldValue = (int)$fieldValue + (int)$modificationValue;
                                    } else {
                                        if ($fieldValue) {
                                            $fieldValue .= $modificationValue;
                                        }
                                    }
                                }
                            }
                        }
                        $data[$row][$fieldName] = $fieldValue;
                    }
                }

                $email = $data[$row]['email'] ?? '';
                if (!$email || !GeneralUtility::validEmail($email)) {
                    // remove entries without valid email
                    unset($data[$row]);
                }
            }
        }

        return array_values($data);
    }

    public static function arrayToCsv(array $data, $separator = ',', $enclosure = '"', $escapeChar = "\\"): string
    {
        $csvString = '';

        $f = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            $quotedRow = array_map(function($field) use ($enclosure, $escapeChar) {
                $escapedField = str_replace($enclosure, $escapeChar . $enclosure, $field);
                return $enclosure . $escapedField . $enclosure;
            }, $row);

            $csvString .= implode($separator, $quotedRow) . "\n";
        }

        fclose($f);

        return $csvString;
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
