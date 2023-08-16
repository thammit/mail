<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class ReportUtility
{
    /**
     * @param string $url
     * @param int $mailPid
     * @return string The URL string
     * @throws SiteNotFoundException
     */
    public static function getUrlStr(string $url, int $mailPid): string
    {
        $urlParts = @parse_url($url);
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($mailPid);

        if ($url && $site->getBase() === ($urlParts['host'] ?? '')) {
            $m = [];
            // do we have an id?
            if (preg_match('/(?:^|&)id=([0-9a-z_]+)/', $urlParts['query'], $m)) {
                $isInt = MathUtility::canBeInterpretedAsInteger($m[1]);
                if ($isInt) {
                    $uid = (int)$m[1];
                    $rootLine = BackendUtility::BEgetRootLine($uid);
                    // array_shift reverses the array (rootline has numeric index in the wrong order!)
                    // $rootLine = array_reverse($rootLine);
                    $pages = array_shift($rootLine);
                    $query = preg_replace('/(?:^|&)id=([0-9a-z_]+)/', '', $urlParts['query']);
                    $url = $pages['title'] . ($query ? ' / ' . $query : '');
                }
            }
        }

        return $url;
    }

    /**
     * This method returns the label for a specified URL.
     * If the page is local and contains a fragment it returns the label of the content element linked to.
     * In any other case it simply fetches the page and extracts the <title> tag content as label
     *
     * @param string $baseURL The base url of the mailing
     * @param string $url The statistics click-URL for which to return a label
     * @param bool $forceFetch When this parameter is set to true the "fetch and extract <title> tag" method will get used
     *
     * @return string The label for the passed $url parameter
     *
     * @todo This method is an absolute performance killer!!!
     * It fetches e.g. every pdfs or videos from external/internal urls only to get its filename!
     */
    public static function getLinkLabel(string $baseURL, string $url, bool $forceFetch = false): string
    {
        $contentTitle = '';

        $urlParts = parse_url($url);
        if (!$forceFetch && (str_starts_with($url, $baseURL))) {
            if (($urlParts['fragment'] ?? false) && (str_starts_with($urlParts['fragment'], 'c'))) {
                // linking directly to a content
                $elementUid = (int)substr($urlParts['fragment'], 1);
                $row = BackendUtility::getRecord('tt_content', $elementUid);
                if ($row) {
                    $contentTitle = BackendUtility::getRecordTitle('tt_content', $row);
                }
            } else {
                $contentTitle = self::getLinkLabel($baseURL, $url, true);
            }
        } else {
            $contentTitle = self::fetchWebpageTitle($url);
        }

        return $contentTitle;
    }

    public static function fetchWebpageTitle(string $url): string
    {
        $client = new Client();

        try {
            $response = $client->head($url);
            if ($response->getStatusCode() === 200) {
                try {
                    $contentType = $response->getHeaderLine('Content-Type');
                    if (!str_contains($contentType, 'text/html')) {
                        return $url;
                    }
                    $body = '';
                    $bodyStream = $client->get($url, ['stream' => true])->getBody();
                    while (!$bodyStream->eof()) {
                        $body .= $bodyStream->read(1024);
                        preg_match('/<title>(.*?)<\/title>/i', $body, $titleMatches);
                        if (isset($titleMatches[1])) {
                            return $titleMatches[1];
                        }
                        unset($titleMatches);
                    }
                } catch (RequestException|GuzzleException $e) {
                }
            }
        } catch (RequestException|GuzzleException $e) {
        }

        return $url;
    }

    public static function showWithPercent(int $pieces, int $total): string
    {
        $str = $pieces ? number_format($pieces) : '0';
        if ($total) {
            $str .= ' / ' . number_format(($pieces / $total * 100), 2) . '%';
        }
        return $str;
    }

}
