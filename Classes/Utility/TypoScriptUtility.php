<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use MEDIAESSENZ\Mail\Domain\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypoScriptUtility
{
    /**
     * Implodes a multi dimensional TypoScript array, $p,
     * into a one-dimensional array (return value)
     *
     * @param array $tsConfig TypoScript structure
     * @param string $prefix Prefix string
     *
     * @return array Imploded TypoScript object string/values
     */
    public static function implodeTSParams(array $tsConfig, string $prefix = ''): array
    {
        $implodeParams = [];
        foreach ($tsConfig as $kb => $val) {
            if (is_array($val)) {
                $implodeParams = array_merge($implodeParams, static::implodeTSParams($val, $prefix . $kb));
            } else {
                $implodeParams[$prefix . $kb] = $val;
            }
        }

        return $implodeParams;
    }

    /**
     * Updates Page TSconfig for a page with $id
     * The function seems to take $pageTS as an array with properties
     * and compare the values with those that already exists for the "object string",
     * $TSconfPrefix, for the page, then sets those values which were not present.
     * $impParams can be supplied as already known Page TSconfig, otherwise it's calculated.
     *
     * THIS DOES NOT CHECK ANY PERMISSIONS. SHOULD IT?
     * More documentation is needed.
     *
     * @param int $pageId Page id
     * @param array $pageTSConfig Page TS array to write
     * @param string $tsConfigPrefix Prefix for object paths
     *
     * @return    void
     *
     * @see implodeTSParams(), getPagesTSconfig()
     */
    public static function updatePagesTSConfig(int $pageId, array $pageTSConfig, string $tsConfigPrefix)
    {
        $done = false;
        if ($pageId > 0) {
            $currentPageTSConfig = static::implodeTSParams(BackendUtility::getPagesTSconfig($pageId));
            $set = [];
            foreach ($pageTSConfig as $key => $value) {
                $value = trim($value);
                $key = $tsConfigPrefix . $key;
                $tempF = isset($currentPageTSConfig[$key]) ? trim($currentPageTSConfig[$key]) : '';
                if (strcmp($tempF, $value)) {
                    $set[$key] = $value;
                }
            }
            if (count($set)) {
                // Get page record and TS config lines
                $pageRecord = BackendUtility::getRecord('pages', $pageId);
                $tsLines = explode(LF, $pageRecord['TSconfig'] ?? '');
                $tsLines = array_reverse($tsLines);
                // Reset the set of changes.
                foreach ($set as $key => $value) {
                    $inserted = 0;
                    foreach ($tsLines as $ki => $kv) {
                        if (substr($kv, 0, strlen($key) + 1) == $key . '=') {
                            $tsLines[$ki] = $key . '=' . $value;
                            $inserted = 1;
                            break;
                        }
                    }
                    if (!$inserted) {
                        $tsLines = array_reverse($tsLines);
                        $tsLines[] = $key . '=' . $value;
                        $tsLines = array_reverse($tsLines);
                    }
                }
                $tsLines = array_reverse($tsLines);

                // store those changes
                $done = GeneralUtility::makeInstance(PagesRepository::class)->updatePageTsConfig($pageId, implode(LF, $tsLines));
            }
        }

        return $done;
    }

    public static function getUserTSConfig(): array
    {
        return BackendUserUtility::getBackendUser()->getTSConfig();
    }
}
