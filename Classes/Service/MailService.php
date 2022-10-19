<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use DOMDocument;
use DOMElement;
use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class MailService
{
    /**
     * @var Mail|null
     */
    protected ?Mail $mail;

    protected array $responseTypesTable = [];
    protected array $returnCodesTable = [];
    protected int $uniqueHtmlResponses = 0;
    protected int $uniquePlainResponses = 0;
    protected int $uniquePingResponses = 0;
    protected int $totalSent = 0;
    protected int $htmlSent = 0;
    protected int $plainSent = 0;

    public function __construct(
        protected LogRepository $logRepository,
        protected AddressRepository $addressRepository,
        protected FrontendUserRepository $frontendUserRepository,
        protected SysDmailMaillogRepository $sysDmailMaillogRepository,
        protected TempRepository $tempRepository
    ) {
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    public function init(Mail $mail): void
    {
        $this->mail = $mail;
        $this->responseTypesTable = $this->changeKeyName($this->sysDmailMaillogRepository->countSysDmailMaillogsResponseTypeByMid($this->mail->getUid()),
            'counter', 'COUNT(*)');
        // Plaintext/HTML
        $res = $this->sysDmailMaillogRepository->countSysDmailMaillogAllByMid($this->mail->getUid());

        /* this function is called to change the key from 'COUNT(*)' to 'counter' */
        $res = $this->changeKeyName($res, 'counter', 'COUNT(*)');

        $textHtml = [];
        foreach ($res as $row2) {
            // 0:No mail; 1:HTML; 2:TEXT; 3:HTML+TEXT
            $textHtml[$row2['format_sent']] = $row2['counter'];
        }

        // Unique responses, html
        $this->uniqueHtmlResponses = $this->sysDmailMaillogRepository->countSysDmailMaillogHtmlByMid($this->mail->getUid());

        // Unique responses, Plain
        $this->uniquePlainResponses = $this->sysDmailMaillogRepository->countSysDmailMaillogPlainByMid($this->mail->getUid());

        // Unique responses, pings
        $this->uniquePingResponses = $this->sysDmailMaillogRepository->countSysDmailMaillogPingByMid($this->mail->getUid());

        $this->totalSent = (int)($textHtml['1'] ?? 0) + (int)($textHtml['2'] ?? 0) + (int)($textHtml['3'] ?? 0);
        $this->htmlSent = (int)($textHtml['1'] ?? 0) + (int)($textHtml['3'] ?? 0);
        $this->plainSent = (int)($textHtml['2'] ?? 0);

        $this->returnCodesTable = $this->sysDmailMaillogRepository->countReturnCode($this->mail->getUid());
        $this->returnCodesTable = $this->changeKeyName($this->returnCodesTable, 'counter', 'COUNT(*)');
    }

    /**
     * @return array
     */
    public function getGeneralData(): array
    {
        $plainSource = '';
        $htmlSource = '';
        if ($this->mail->isHtml()) {
            if ($this->mail->isExternal()) {
                $htmlSource = $this->mail->getHtmlParams();
            } else {
                $htmlSource = BackendUtility::getRecord('pages', $this->mail->getPage(), 'title')['title'];
                if ($this->mail->getHtmlParams()) {
                    $htmlSource .= '; ' . $this->mail->getHtmlParams();
                }
            }
        }
        if ($this->mail->isPlain()) {
            if ($this->mail->isExternal()) {
                $plainSource = $this->mail->getPlainParams();
            } else {
                $plainSource = BackendUtility::getRecord('pages', $this->mail->getPage(), 'title')['title'];
                if ($this->mail->getPlainParams()) {
                    $plainSource .= '; ' .  $this->mail->getPlainParams();
                }
            }
        }

        return [
            'source' => rtrim(($plainSource ? $plainSource . ' / ': '') . ($htmlSource ? $htmlSource . ' / ' : ''), ' /'),
            'type' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'type', $this->mail->getType()),
            'priority' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'priority', $this->mail->getPriority()),
            'sendOptions' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'send_options',
                    $this->mail->getSendOptions()) . ($this->mail->getAttachment() ? '; ' : ''),
            'flowedFormat' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'flowed_format', $this->mail->isFlowedFormat()),
            'includeMedia' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'include_media', $this->mail->isIncludeMedia()),
        ];
    }

    /**
     *
     * @return array
     */
    public function getPerformanceData(): array
    {
        return [
            'totalSent' => $this->totalSent,
            'htmlSent' => $this->htmlSent,
            'plainSent' => $this->plainSent,
            'returned' => $this->showWithPercent($this->responseTypesTable['-127']['counter'] ?? 0, $this->totalSent),
            'htmlViewed' => $this->showWithPercent($this->uniquePingResponses, $this->htmlSent),
            'totalResponses' => ($this->responseTypesTable['1']['counter'] ?? 0) + ($this->responseTypesTable['2']['counter'] ?? 0),
            'htmlResponses' => $this->responseTypesTable['1']['counter'] ?? '0',
            'plainResponses' => $this->responseTypesTable['2']['counter'] ?? '0',
            'uniqueResponsesTotal' => $this->showWithPercent($this->uniqueHtmlResponses + $this->uniquePlainResponses, $this->totalSent),
            'uniqueResponsesHtml' => $this->showWithPercent($this->uniqueHtmlResponses, $this->htmlSent),
            'uniqueResponsesPlain' => $this->showWithPercent($this->uniquePlainResponses, $this->plainSent ?: $this->htmlSent),
            'totalResponsesVsUniqueResponses' => ($this->uniqueHtmlResponses + $this->uniquePlainResponses ? number_format(($this->responseTypesTable['1']['counter'] + $this->responseTypesTable['2']['counter']) / ($this->uniqueHtmlResponses + $this->uniquePlainResponses),
                2) : '-'),
            'htmlResponsesVsUniqueResponses' => ($this->uniqueHtmlResponses ? number_format(($this->responseTypesTable['1']['counter']) / ($this->uniqueHtmlResponses),
                2) : '-'),
            'plainResponsesVsUniqueResponses' => ($this->uniquePlainResponses ? number_format(($this->responseTypesTable['2']['counter']) / ($this->uniquePlainResponses),
                2) : '-'),
        ];
    }

    public function getReturnedData(): array
    {
        $responsesFailed = (int)($this->responseTypesTable['-127']['counter'] ?? 0);
        return [
            'total' => number_format($responsesFailed),
            'unknown' => $this->showWithPercent(($this->returnCodesTable['550']['counter'] ?? 0) + ($this->returnCodesTable['553']['counter'] ?? 0),
                $responsesFailed),
            'full' => $this->showWithPercent(($this->returnCodesTable['551']['counter'] ?? 0), $responsesFailed),
            'badHost' => $this->showWithPercent(($this->returnCodesTable['552']['counter'] ?? 0), $responsesFailed),
            'headerError' => $this->showWithPercent(($this->returnCodesTable['554']['counter'] ?? 0), $responsesFailed),
            'reasonUnknown' => $this->showWithPercent(($this->returnCodesTable['-1']['counter'] ?? 0), $responsesFailed),
        ];
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getReturnedDetailsData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid());
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getUnknownData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), [550, 553]);
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getMailboxFullData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), [551]);
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getBadHostData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), [552]);
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getBadHeaderData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), [554]);
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getReasonUnknownData(): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), [-1]);
    }

    /**
     * @param array $data
     * @return int
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function disableRecipients(array $data): int
    {
        $affectedRecipients = 0;
        $affectedRecipients += $data['addresses'] ? $this->disableAddresses($data['addresses']) : 0;
        $affectedRecipients += $data['frontendUsers'] ? $this->disableFrontendUsers($data['frontendUsers']) : 0;

        return $affectedRecipients;
    }

    /**
     * @param array $data
     * @return void
     */
    public function csvDownloadRecipients(array $data): void
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

        CsvUtility::downloadCSV($emails);
    }

    /**
     * @return array
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function getResponsesData(): array
    {
        $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        $htmlUrlsTable = $this->sysDmailMaillogRepository->findMostPopularLinks($this->mail->getUid());
        $htmlUrlsTable = $this->changeKeyName($htmlUrlsTable, 'counter', 'COUNT(*)');

        // Most popular links, plain:
        $plainUrlsTable = $this->sysDmailMaillogRepository->findMostPopularLinks($this->mail->getUid(), 2);
        $plainUrlsTable = $this->changeKeyName($plainUrlsTable, 'counter', 'COUNT(*)');
        $unpackedMail = unserialize(base64_decode($this->mail->getMailContent()));
        $urlCounter = [];
        $urlCounter['total'] = [];
        // Traverse html urls:
        $urlCounter['html'] = [];
        if (count($htmlUrlsTable) > 0) {
            foreach ($htmlUrlsTable as $id => $c) {
                $urlCounter['html'][$id]['counter'] = $urlCounter['total'][$id]['counter'] = $c['counter'];
            }
        }
        $urlArr = [];

        $urlMd5Map = [];
        if (is_array($unpackedMail['html']['hrefs'] ?? false)) {
            foreach ($unpackedMail['html']['hrefs'] as $k => $v) {
                // convert &amp; of query params back
                $urlArr[$k] = html_entity_decode($v['absRef']);
                $urlMd5Map[md5($v['absRef'])] = $k;
            }
        }
        if (is_array($unpackedMail['plain']['link_ids'] ?? false)) {
            foreach ($unpackedMail['plain']['link_ids'] as $k => $v) {
                $urlArr[-$k] = $v;
            }
        }

        $mappedPlainUrlsTable = [];
        foreach ($plainUrlsTable as $id => $c) {
            $url = $urlArr[intval($id)];
            if (isset($urlMd5Map[md5($url)])) {
                $mappedPlainUrlsTable[$urlMd5Map[md5($url)]] = $c;
            } else {
                $mappedPlainUrlsTable[$id] = $c;
            }
        }

        // Traverse plain urls:
        $urlCounter['plain'] = [];
        foreach ($mappedPlainUrlsTable as $id => $c) {
            // Look up plain url in html urls
            $htmlLinkFound = false;
            foreach ($urlCounter['html'] as $htmlId => $_) {
                if ($urlArr[$id] == $urlArr[$htmlId]) {
                    $urlCounter['html'][$htmlId]['plainId'] = $id;
                    $urlCounter['html'][$htmlId]['plainCounter'] = $c['counter'];
                    $urlCounter['total'][$htmlId]['counter'] = $urlCounter['total'][$htmlId]['counter'] + $c['counter'];
                    $htmlLinkFound = true;
                    break;
                }
            }
            if (!$htmlLinkFound) {
                $urlCounter['plain'][$id]['counter'] = $c['counter'];
                $urlCounter['total'][$id]['counter'] = $urlCounter['total'][$id]['counter'] + $c['counter'];
            }
        }
        arsort($urlCounter['total']);
        arsort($urlCounter['html']);
        arsort($urlCounter['plain']);
        reset($urlCounter['total']);

        // HTML mails
        $htmlLinks = [];
        if ($this->mail->getSendOptions() & 0x2) {
            $htmlContent = $unpackedMail['html']['content'];

            if (is_array($unpackedMail['html']['hrefs'])) {
                foreach ($unpackedMail['html']['hrefs'] as $jumpurlId => $data) {
                    $htmlLinks[$jumpurlId] = [
                        'url' => $data['ref'],
                        'label' => '',
                    ];
                }
            }

            // Parse mail body
            $dom = new DOMDocument;
            @$dom->loadHTML($htmlContent);
            $links = [];
            // Get all links
            foreach ($dom->getElementsByTagName('a') as $node) {
                $links[] = $node;
            }

            // Process all links found
            foreach ($links as $link) {
                /* @var DOMElement $link */
                $url = $link->getAttribute('href');

                if (empty($url)) {
                    // Drop a tags without href
                    continue;
                }

                if (str_starts_with($url, 'mailto:')) {
                    // Drop mail links
                    continue;
                }

                if (str_starts_with($url, '#')) {
                    // Drop internal anker links
                    continue;
                }

                if (!str_contains($url, '=')) {
                    continue;
                }

                $parsedUrl = GeneralUtility::explodeUrl2Array($url);

                if (!array_key_exists('jumpurl', $parsedUrl)) {
                    // Ignore non-jumpurl links
                    continue;
                }

                $jumpurlId = $parsedUrl['jumpurl'];
                $targetUrl = $htmlLinks[$jumpurlId]['url'];

                $title = $link->getAttribute('title');

                if (!empty($title)) {
                    // no title attribute
                    $label = '<span title="' . $title . '">' . GeneralUtility::fixed_lgd_cs(substr($targetUrl, -40), 40) . '</span>';
                } else {
                    $label = '<span title="' . $targetUrl . '">' . GeneralUtility::fixed_lgd_cs(substr($targetUrl, -40), 40) . '</span>';
                }

                $htmlLinks[$jumpurlId]['label'] = $label;
            }
        }

        $tblLines = [];
        $html = false;

        foreach ($urlCounter['total'] as $id => $_) {
            // $id is the jumpurl ID
            $origId = $id;
            $id = abs(intval($id));
            $url = $htmlLinks[$id]['url'] ?: $urlArr[$origId];
            // a link to this host?
            $uParts = @parse_url($url);
            $urlstr = $this->getUrlStr($uParts);
            $label = $this->getLinkLabel($url, $urlstr, false, $htmlLinks[$id]['label']);
            if (isset($urlCounter['html'][$id]['plainId'])) {
                $tblLines[] = [
                    $label,
                    $id,
                    $urlCounter['html'][$id]['plainId'],
                    $urlCounter['total'][$origId]['counter'],
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['html'][$id]['plainCounter'],
                    $urlstr,
                ];
            } else {
                $html = !empty($urlCounter['html'][$id]['counter']);
                $tblLines[] = [
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : $id),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$origId]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$origId]['counter'],
                    $urlstr,
                ];
            }
        }

        // go through all links that were not clicked yet and that have a label
        $clickedLinks = array_keys($urlCounter['total']);
        foreach ($urlArr as $id => $link) {
            if (!in_array($id, $clickedLinks) && (isset($htmlLinks['id']))) {
                // a link to this host?
                $uParts = @parse_url($link);
                $urlstr = $this->getUrlStr($uParts);
                $label = $htmlLinks[$id]['label'] . ' (' . ($urlstr ?: '/') . ')';
                $tblLines[] = [
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : abs($id)),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$id]['counter'],
                    $link,
                ];
            }
        }

        return $tblLines;
    }

    // todo make static
    protected function showWithPercent(int $pieces, int $total): string
    {
        $str = $pieces ? number_format($pieces) : '0';
        if ($total) {
            $str .= ' / ' . number_format(($pieces / $total * 100), 2) . '%';
        }
        return $str;
    }

    /**
     * Switch the key of an array
     * todo make static
     *
     * @param array $array
     * @param string $newkey
     * @param string $oldkey
     * @return array $array
     */
    private function changeKeyName(array $array, string $newkey, string $oldkey): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->changeKeyName($value, $newkey, $oldkey);
            } else {
                $array[$newkey] = $array[$oldkey];
            }
        }
        unset($array[$oldkey]);
        return $array;
    }

    /**
     * Generates a string for the URL
     *
     * @param array $urlParts The parts of the URL
     *
     * @return string The URL string
     * @throws SiteNotFoundException
     */
    public function getUrlStr(array $urlParts): string
    {
        $baseUrl = $this->getBaseURL();

//        $siteUrl = $request->getAttribute('normalizedParams')->getSiteUrl();
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($this->mail->getPid());
        $siteUrl = $site->getBase();

        if ($urlParts && $siteUrl == $urlParts['host']) {
            $m = [];
            // do we have an id?
            if (preg_match('/(?:^|&)id=([0-9a-z_]+)/', $urlParts['query'], $m)) {
                $isInt = MathUtility::canBeInterpretedAsInteger($m[1]);
                if ($isInt) {
                    $uid = intval($m[1]);
                }
//                @TODO
//                 else {
//                     // initialize the page selector
//                     /** @var PageRepository $sys_page */
//                     $sys_page = GeneralUtility::makeInstance(PageRepository::class);
//                     $sys_page->init(true);
//                     $uid = $sys_page->getPageIdFromAlias($m[1]);
//                 }
                $rootLine = BackendUtility::BEgetRootLine($uid);
                $pages = array_shift($rootLine);
                // array_shift reverses the array (rootline has numeric index in the wrong order!)
                $rootLine = array_reverse($rootLine);
                $query = preg_replace('/(?:^|&)id=([0-9a-z_]+)/', '', $urlParts['query']);
                $urlstr = GeneralUtility::fixed_lgd_cs($pages['title'], 50) . GeneralUtility::fixed_lgd_cs(($query ? ' / ' . $query : ''), 20);
            } else {
                $urlstr = $baseUrl . substr($urlParts['path'], 1);
                $urlstr .= $urlParts['query'] ? '?' . $urlParts['query'] : '';
                $urlstr .= $urlParts['fragment'] ? '#' . $urlParts['fragment'] : '';
            }
        } else {
            $urlstr = ($urlParts['host'] ? $urlParts['scheme'] . '://' . $urlParts['host'] : $baseUrl) . $urlParts['path'];
            $urlstr .= $urlParts['query'] ? '?' . $urlParts['query'] : '';
            $urlstr .= $urlParts['fragment'] ? '#' . $urlParts['fragment'] : '';
        }

        return $urlstr;
    }

    /**
     * Get baseURL of the FE
     * force http if UseHttpToFetch is set
     *
     * @return string the baseURL
     */
    public function getBaseURL(): string
    {
//        $baseUrl = $this->siteUrl;
//        $baseUrl = $this->mail->getRedirectUrl();
        $baseUrl = BackendDataUtility::getBaseUrl($this->mail->getPage() ?: $this->mail->getPid());

        // if fetching the newsletter using http, set the url to http here
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['UseHttpToFetch'] == 1) {
            $baseUrl = str_replace('https', 'http', $baseUrl);
        }

        return $baseUrl;
    }

    /**
     * This method returns the label for a specified URL.
     * If the page is local and contains a fragment it returns the label of the content element linked to.
     * In any other case it simply fetches the page and extracts the <title> tag content as label
     *
     * @param string $url The statistics click-URL for which to return a label
     * @param string $urlStr A processed variant of the url string. This could get appended to the label???
     * @param bool $forceFetch When this parameter is set to true the "fetch and extract <title> tag" method will get used
     * @param string $linkedWord The word to be linked
     *
     * @return string The label for the passed $url parameter
     */
    public function getLinkLabel(string $url, string $urlStr, bool $forceFetch = false, string $linkedWord = ''): string
    {
        $pathSite = $this->getBaseURL();
        $label = $linkedWord;
        $contentTitle = '';

        $urlParts = parse_url($url);
        if (!$forceFetch && (str_starts_with($url, $pathSite))) {
            if ($urlParts['fragment'] && (str_starts_with($urlParts['fragment'], 'c'))) {
                // linking directly to a content
                $elementUid = intval(substr($urlParts['fragment'], 1));
                $row = BackendUtility::getRecord('tt_content', $elementUid);
                if ($row) {
                    $contentTitle = BackendUtility::getRecordTitle('tt_content', $row);
                }
            } else {
                $contentTitle = $this->getLinkLabel($url, $urlStr, true);
            }
        } else {
            if (empty($urlParts['host']) && (!str_starts_with($url, $pathSite))) {
                // it's internal
                $url = $pathSite . $url;
            }

            $content = GeneralUtility::getURL($url);
            if (is_string($content) && preg_match('/<\s*title\s*>(.*)<\s*\/\s*title\s*>/i', $content, $matches)) {
                // get the page title
                $contentTitle = GeneralUtility::fixed_lgd_cs(trim($matches[1]), 50);
            } else {
                // file?
                $file = GeneralUtility::split_fileref($url);
                $contentTitle = $file['file'];
            }
        }

        $pageTSConfiguration = BackendUtility::getPagesTSconfig($this->mail->getPid())['mod.']['web_modules.']['mail.'] ?? [];
        if ($pageTSConfiguration['showContentTitle'] == 1) {
            $label = $contentTitle;
        }

        if ($pageTSConfiguration['prependContentTitle'] == 1) {
            $label = $contentTitle . ' (' . $linkedWord . ')';
        }

//        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['getLinkLabel'])) {
//            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['getLinkLabel'] as $funcRef) {
//                $params = ['pObj' => &$this, 'url' => $url, 'urlStr' => $urlStr, 'label' => $label];
//                $label = GeneralUtility::callUserFunction($funcRef, $params, $this);
//            }
//        }

        // Fallback to url
        if ($label === '') {
            $label = $url;
        }

        if (isset($this->pageTSConfiguration['maxLabelLength']) && ($this->pageTSConfiguration['maxLabelLength'] > 0)) {
            $label = GeneralUtility::fixed_lgd_cs($label, $this->pageTSConfiguration['maxLabelLength']);
        }

        return $label;
    }

    /**
     * @param array $addresses array of addresses
     *
     * @return int total of disabled records
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function disableAddresses(array $addresses): int
    {
        /** @var Address $address */
        foreach ($addresses as $address) {
            $address->setHidden(true);
            $this->addressRepository->update($address);
        }

        return count($addresses);
    }

    /**
     * @param array $frontendUsers array of frontend users
     *
     * @return int total of disabled records
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function disableFrontendUsers(array $frontendUsers): int
    {
        /** @var FrontendUser $frontendUser */
        foreach ($frontendUsers as $frontendUser) {
            $frontendUser->setDisable(true);
            $this->addressRepository->update($frontendUser);
        }

        return count($frontendUsers);
    }

    public function getQueryBuilder($table): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }

    protected function getConnection(string $table): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
    }

    /**
     * @return array
     */
    public function getResponseTypesTable(): array
    {
        return $this->responseTypesTable;
    }

    /**
     * @return int
     */
    public function getUniqueHtmlResponses(): int
    {
        return $this->uniqueHtmlResponses;
    }

    /**
     * @return int
     */
    public function getUniquePlainResponses(): int
    {
        return $this->uniquePlainResponses;
    }

    /**
     * @return int
     */
    public function getUniquePingResponses(): int
    {
        return $this->uniquePingResponses;
    }

    /**
     * @return int
     */
    public function getTotalSent(): int
    {
        return $this->totalSent;
    }

    /**
     * @return int
     */
    public function getHtmlSent(): int
    {
        return $this->htmlSent;
    }

    /**
     * @return int
     */
    public function getPlainSent(): int
    {
        return $this->plainSent;
    }

}
