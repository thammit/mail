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
     * @param string $url The statistics click-URL for which to return a label
     * @param string $baseURL The base url of the mailing
     *
     * @return string The label for the passed $url parameter
     */
    public static function getContentTitle(string $url, string $baseURL): string
    {
        if (str_starts_with($url, $baseURL)) {
            $fragment = parse_url($url, PHP_URL_FRAGMENT);
            if ($fragment && str_starts_with($fragment, 'c')) {
                // linking directly to a content
                $elementUid = (int)substr($fragment, 1);
                $row = BackendUtility::getRecord('tt_content', $elementUid);
                if ($row) {
                    return BackendUtility::getRecordTitle('tt_content', $row);
                }
            }
            // return $url;
        }

        return self::determineDocumentTitle($url);
    }

    public static function determineDocumentTitle(string $url): string
    {
        $client = new Client();

        try {
            $response = $client->head($url);
            if ($response->getStatusCode() === 200) {
                if (str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
                    try {
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
                } else {
                    return basename(parse_url($url, PHP_URL_PATH));
                }
            }
        } catch (RequestException|GuzzleException $e) {
        }

        return $url;
    }

    public static function determineDocumentIconIdentifier($url): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        return match ($extension) {
            'pdf' => 'mimetypes-pdf',
            'xls', 'xlsx' => 'mimetypes-excel',
            'doc', 'docx' => 'mimetypes-word',
            'ppt', 'pptx' => 'mimetypes-powerpoint',
            'jpg', 'jpeg', 'png', 'gif' => 'mimetypes-media-image',
            'zip', 'rar' => 'mimetypes-compressed',
            'mp3', 'wav', 'aac' => 'mimetypes-media-audio',
            'mp4', 'mov', 'avi' => 'mimetypes-media-video',
            'htm', 'html' => 'mimetypes-text-html',
            default => 'mimetypes-other-other',
        };
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
