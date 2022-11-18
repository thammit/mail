<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use JetBrains\PhpStorm\NoReturn;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
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
        $fh = tmpfile();
        fwrite($fh, trim($str));
        fseek($fh, 0);
        $lines = [];
        if ($separator == 'tab') {
            $separator = "\t";
        }
        while ($data = fgetcsv($fh, 1000, $separator)) {
            $lines[] = $data;
        }

        fclose($fh);

        $out = [];
        if (count($lines) > 0) {
            // Analyse if first line contains field names.
            // Required is it that every value is either
            // 1) found in the list fieldsList in this class,
            // 2) the value is empty (value omitted then) or
            // 3) the field starts with "user_".
            // In addition, fields may be prepended with "[code]".
            // This is used if the incoming value is true in which case '+[value]'
            // adds that number to the field value (accumulation) and '=[value]'
            // overrides any existing value in the field
            $firstRow = $lines[0];
            try {
                $fieldList = array_merge($fieldList, explode(',', ConfigurationUtility::getExtensionConfiguration('additionalRecipientFields')));
            } catch (ExtensionConfigurationPathDoesNotExistException|ExtensionConfigurationExtensionNotConfiguredException) {
            }
            $fieldName = 1;
            $fieldOrder = [];

            foreach ($firstRow as $value) {
                $fName = '';
                $probe = preg_split('|[\[\]]|', $value);
                if (is_array($probe)) {
                    [$fName, $fConf] = count($probe) === 2 ? $probe : [$probe[0], ''];
                }
                $fName = trim($fName ?? '');
                $fConf = trim($fConf ?? '');
                $fieldOrder[] = [$fName, $fConf];
                if ($fName && !str_starts_with($fName, 'user_') && !in_array($fName, $fieldList)) {
                    $fieldName = 0;
                    break;
                }
            }
            // If not field list, then:
            if (!$fieldName) {
                $fieldOrder = [
                    ['name'],
                    ['email'],
                ];
            }
            // Re-map values
            if ($fieldName) {
                // Advance pointer if the first line was field names
                next($lines);
            }

            $c = 0;
            foreach ($lines as $data) {
                // Must be a line with content.
                // This sorts out entries with one key which is empty. Those are empty lines.
                if (count($data) > 1 || $data[0]) {
                    // Traverse fieldOrder and map values over
                    foreach ($fieldOrder as $kk => $fN) {
                        if ($fN[0]) {
                            if ($fN[1] ?? false) {
                                // If is true
                                if (trim($data[$kk])) {
                                    if (str_starts_with($fN[1], '=')) {
                                        $out[$c][$fN[0]] = trim(substr($fN[1], 1));
                                    } else if (str_starts_with($fN[1], '+')) {
                                        $out[$c][$fN[0]] .= substr($fN[1], 1);
                                    }
                                }
                            } else {
                                $out[$c][$fN[0]] = trim($data[$kk]);
                            }
                        }
                    }
                    $c++;
                }
            }
        }
        return $out;
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
     *
     * @return array array of charset-converted values
     * @see \TYPO3\CMS\Core\Charset\CharsetConverter::conv[]
     */
    public static function convertCharset(array $data, $targetCharset, $dbCharset = 'utf-8'): array
    {
        // todo check database charset
        if ($dbCharset !== strtolower($targetCharset)) {
            $converter = GeneralUtility::makeInstance(CharsetConverter::class);
            foreach ($data as $k => $v) {
                $data[$k] = $converter->conv($v, strtolower($targetCharset), $dbCharset);
            }
        }
        return $data;
    }

    /**
     * Send csv values as download by sending appropriate HTML header
     *
     * @param array $idArr Values to be put into csv
     *
     * @return void Sent HML header for a file download
     */
    #[NoReturn] public static function downloadCSV(array $idArr): void
    {
        $lines = [];
        if (count($idArr)) {
            reset($idArr);
            $lines[] = \TYPO3\CMS\Core\Utility\CsvUtility::csvValues(array_keys(current($idArr)));

            foreach ($idArr as $rec) {
                $lines[] = \TYPO3\CMS\Core\Utility\CsvUtility::csvValues($rec);
            }
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=mail_recipients_' . date('dmy-Hi') . '.csv');
        echo implode(CR . LF, $lines);
        exit;
    }
}
