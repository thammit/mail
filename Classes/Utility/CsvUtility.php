<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CsvUtility
{
    /**
     * Parsing csv-formated text to an array
     *
     * @param string $str String in csv-format
     * @param string $sep Separator
     *
     * @return array Parsed csv in an array
     */
    public function getCsvValues(string $str, string $sep = ','): array
    {
        $fh = tmpfile();
        fwrite($fh, trim($str));
        fseek($fh, 0);
        $lines = [];
        if ($sep == 'tab') {
            $sep = "\t";
        }
        while ($data = fgetcsv($fh, 1000, $sep)) {
            $lines[] = $data;
        }

        fclose($fh);
        return $lines;
    }

    /**
     * Parse CSV lines into array form
     *
     * @param array $lines CSV lines
     * @param string $fieldList List of the fields
     *
     * @return array parsed CSV values
     */
    public function rearrangeCsvValues(array $lines, string $fieldList = ''): array
    {
        $out = [];
        if (is_array($lines) && count($lines) > 0) {
            // Analyse if first line is fieldnames.
            // Required is it that every value is either
            // 1) found in the list fieldsList in this class,
            // 2) the value is empty (value omitted then) or
            // 3) the field starts with "user_".
            // In addition fields may be prepended with "[code]".
            // This is used if the incoming value is true in which case '+[value]'
            // adds that number to the field value (accummulation) and '=[value]'
            // overrides any existing value in the field
            $first = $lines[0];
            $fieldListArr = explode(',', $fieldList);
            if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
                $fieldListArr = array_merge($fieldListArr, explode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']));
            }
            $fieldName = 1;
            $fieldOrder = [];

            foreach ($first as $v) {
                [$fName, $fConf] = preg_split('|[\[\]]|', $v);
                $fName = trim($fName);
                $fConf = trim($fConf);
                $fieldOrder[] = [$fName, $fConf];
                if ($fName && substr($fName, 0, 5) != 'user_' && !in_array($fName, $fieldListArr)) {
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
            reset($lines);
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
                            if ($fN[1]) {
                                // If is true
                                if (trim($data[$kk])) {
                                    if (substr($fN[1], 0, 1) == '=') {
                                        $out[$c][$fN[0]] = trim(substr($fN[1], 1));
                                    } else if (substr($fN[1], 0, 1) == '+') {
                                        $out[$c][$fN[0]] += substr($fN[1], 1);
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
    public static function downloadCSV(array $idArr)
    {
        $lines = [];
        if (count($idArr)) {
            reset($idArr);
            $lines[] = \TYPO3\CMS\Core\Utility\CsvUtility::csvValues(array_keys(current($idArr)));

            reset($idArr);
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
