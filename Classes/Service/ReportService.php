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
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class ReportService
{
    /**
     * @var Mail|null
     */
    protected ?Mail $mail;

    protected array $responseTypesTable = [];

    public function __construct(
        protected LogRepository $logRepository,
        protected AddressRepository $addressRepository,
        protected FrontendUserRepository $frontendUserRepository
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
        $this->responseTypesTable = $this->logRepository->findResponseTypesByMail($this->mail->getUid());
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
                (string)$this->mail->getSendOptions()) . ($this->mail->getAttachment() ? '; ' : ''),
            'flowedFormat' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'flowed_format', $this->mail->isFlowedFormat()),
            'includeMedia' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'include_media', $this->mail->isIncludeMedia()),
        ];
    }

    /**
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function getPerformanceData(): array
    {
        $formatSent = $this->logRepository->findFormatSentByMail($this->mail->getUid());
        $totalSent = (int)($formatSent[SendFormat::PLAIN] ?? 0) + (int)($formatSent[SendFormat::HTML] ?? 0) + (int)($formatSent[SendFormat::BOTH] ?? 0);
        $htmlSent = (int)($formatSent[SendFormat::HTML] ?? 0) + (int)($formatSent[SendFormat::BOTH] ?? 0);
        $plainSent = (int)($formatSent[SendFormat::PLAIN] ?? 0);

        $uniqueHtmlResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::HTML);
        $uniquePlainResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::PLAIN);
        $uniqueResponsesTotal = $uniqueHtmlResponses + $uniquePlainResponses;
        $uniquePingResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::PING);

        $htmlResponses = $this->responseTypesTable[ResponseType::HTML] ?? 0;
        $plainResponses = $this->responseTypesTable[ResponseType::PLAIN] ?? 0;
        $failedResponses = $this->responseTypesTable[ResponseType::FAILED] ?? 0;
        $totalResponses = $htmlResponses + $plainResponses;

        return [
            'htmlSent' => $htmlSent,
            'plainSent' => $plainSent,
            'totalSent' => $totalSent,
            'returned' => $this->showWithPercent($failedResponses, $totalSent),
            'htmlResponses' => $htmlResponses,
            'plainResponses' => $plainResponses,
            'totalResponses' => $totalResponses,
            'htmlViewed' => $this->showWithPercent($uniquePingResponses, $htmlSent),
            'uniqueResponsesTotal' => $this->showWithPercent($uniqueResponsesTotal, $totalSent),
            'uniqueResponsesHtml' => $this->showWithPercent($uniqueHtmlResponses, $htmlSent),
            'uniqueResponsesPlain' => $this->showWithPercent($uniquePlainResponses, $plainSent),
            'totalResponsesVsUniqueResponses' => $uniqueResponsesTotal ? number_format($totalResponses / $uniqueResponsesTotal,2) : '-',
            'htmlResponsesVsUniqueResponses' => $uniqueHtmlResponses ? number_format($htmlResponses / $uniqueHtmlResponses, 2) : '-',
            'plainResponsesVsUniqueResponses' => $uniquePlainResponses ? number_format($plainResponses / $uniquePlainResponses, 2) : '-',
        ];
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function getReturnedData(): array
    {
        $responsesFailed = (int)($this->responseTypesTable['-127'] ?? 0);
        $returnCodesTable = $this->logRepository->findReturnCodesByMail($this->mail->getUid());

        return [
            'total' => number_format($responsesFailed),
            'unknown' => $this->showWithPercent(($returnCodesTable['550'] ?? 0) + ($returnCodesTable['553'] ?? 0),
                $responsesFailed),
            'full' => $this->showWithPercent(($returnCodesTable['551'] ?? 0), $responsesFailed),
            'badHost' => $this->showWithPercent(($returnCodesTable['552'] ?? 0), $responsesFailed),
            'headerError' => $this->showWithPercent(($returnCodesTable['554'] ?? 0), $responsesFailed),
            'reasonUnknown' => $this->showWithPercent(($returnCodesTable['-1'] ?? 0), $responsesFailed),
        ];
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     * @throws DBALException
     */
    public function getReturnedDetailsData(array $returnCodes = []): array
    {
        return $this->logRepository->findFailedRecipientsByMailAndReturnCodeGroupedByRecipientTable($this->mail->getUid(), $returnCodes);
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
        $mostPopularHtmlLinks = $this->logRepository->findMostPopularLinksByMailAndResponseType($this->mail->getUid());
        $mostPopularPlainLinks = $this->logRepository->findMostPopularLinksByMailAndResponseType($this->mail->getUid(), ResponseType::PLAIN);
        $mailContent = unserialize(base64_decode($this->mail->getMailContent()));
        $urlCounter = [];
        $urlCounter['total'] = [];
        $urlCounter['html'] = [];

        if (count($mostPopularHtmlLinks) > 0) {
            foreach ($mostPopularHtmlLinks as $urlId => $counter) {
                $urlCounter['html'][$urlId]['counter'] = $urlCounter['total'][$urlId]['counter'] = $counter;
            }
        }
        $urlArr = [];

        $urlMd5Map = [];
        if (is_array($mailContent['html']['hrefs'] ?? false)) {
            foreach ($mailContent['html']['hrefs'] as $k => $hrefValue) {
                // convert &amp; of query params back
                $urlArr[$k] = html_entity_decode($hrefValue['absRef']);
                $urlMd5Map[md5($hrefValue['absRef'])] = $k;
            }
        }
        if (is_array($mailContent['plain']['link_ids'] ?? false)) {
            foreach ($mailContent['plain']['link_ids'] as $k => $v) {
                $urlArr[-$k] = $v;
            }
        }

        $mappedPlainUrlsTable = [];
        foreach ($mostPopularPlainLinks as $id => $counter) {
            $url = $urlArr[intval($id)];
            if (isset($urlMd5Map[md5($url)])) {
                $mappedPlainUrlsTable[$urlMd5Map[md5($url)]] = $counter;
            } else {
                $mappedPlainUrlsTable[$id] = $counter;
            }
        }

        // Traverse plain urls:
        $urlCounter['plain'] = [];
        foreach ($mappedPlainUrlsTable as $id => $counter) {
            // Look up plain url in html urls
            $htmlLinkFound = false;
            foreach ($urlCounter['html'] as $htmlId => $_) {
                if ($urlArr[$id] == $urlArr[$htmlId]) {
                    $urlCounter['html'][$htmlId]['plainId'] = $id;
                    $urlCounter['html'][$htmlId]['plainCounter'] = $counter;
                    $urlCounter['total'][$htmlId]['counter'] += $counter;
                    $htmlLinkFound = true;
                    break;
                }
            }
            if (!$htmlLinkFound) {
                $urlCounter['plain'][$id]['counter'] = $counter;
                $urlCounter['total'][$id]['counter'] += $counter;
            }
        }
        arsort($urlCounter['total']);
        arsort($urlCounter['html']);
        arsort($urlCounter['plain']);
        reset($urlCounter['total']);

        // HTML mails
        $htmlLinks = [];
        if ($this->mail->isHtml()) {
            $htmlContent = $mailContent['html']['content'];

            if (is_array($mailContent['html']['hrefs'])) {
                foreach ($mailContent['html']['hrefs'] as $jumpurlId => $jumpurlData) {
                    $htmlLinks[$jumpurlId] = [
                        'url' => $jumpurlData['ref'],
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

                if (empty($url) || str_starts_with($url, 'mailto:') || str_starts_with($url, '#') || !str_contains($url, '=')) {
                    // Drop tags without href / mail links / internal anker
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

                $htmlLinks[$jumpurlId]['label'] = $targetUrl;
                $htmlLinks[$jumpurlId]['title'] = !empty($title) ? $title : $targetUrl;
            }
        }

        $data = [];
        $html = false;

        $clickedLinks = array_keys($urlCounter['total']);

        foreach ($clickedLinks as $id) {
            // $id is the jumpurl ID
            $origId = $id;
            $id = abs(intval($id));
            $url = $htmlLinks[$id]['url'] ?: $urlArr[$origId];
            // a link to this host?
            $urlstr = $this->getUrlStr($url);
            $label = $this->getLinkLabel($url, $urlstr, false, $htmlLinks[$id]['label']);
            if (isset($urlCounter['html'][$id]['plainId'])) {
                $data[] = [
                    'label' => $label,
                    'title' => $htmlLinks[$id]['title'],
                    'totalCounter' => $urlCounter['total'][$origId]['counter'],
                    'htmlCounter' => $urlCounter['html'][$id]['counter'],
                    'plainCounter' => $urlCounter['html'][$id]['plainCounter'],
                    'url' => $urlstr,
                ];
            } else {
                $html = !empty($urlCounter['html'][$id]['counter']);
                $data[] = [
                    'label' => $label,
                    'title' => $htmlLinks[$id]['title'],
                    'totalCounter' => ($html ? $urlCounter['html'][$id]['counter'] ?? 0 : $urlCounter['plain'][$origId]['counter'] ?? 0),
                    'htmlCounter' => $urlCounter['html'][$id]['counter'] ?? 0,
                    'plainCounter' => $urlCounter['plain'][$origId]['counter'] ?? 0,
                    'url' => $urlstr,
                ];
            }
        }

        // go through all links that were not clicked yet and that have a label
        foreach ($urlArr as $id => $link) {
            if (!in_array($id, $clickedLinks) && (isset($htmlLinks['id']))) {
                // a link to this host?
                $urlstr = $this->getUrlStr($link);
                $label = $htmlLinks[$id]['label'] . ' (' . ($urlstr ?: '/') . ')';
                $data[] = [
                    'label' => $label,
                    'title' => $htmlLinks[$id]['title'],
                    'totalCounter' => ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
                    'htmlCounter' => $urlCounter['html'][$id]['counter'],
                    'plainCounter' => $urlCounter['plain'][$id]['counter'],
                    'url' => $link,
                ];
            }
        }

        return $data;
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
     * @param string $url
     *
     * @return string The URL string
     * @throws SiteNotFoundException
     */
    public function getUrlStr(string $url): string
    {
        $urlParts = @parse_url($url);
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($this->mail->getPid());

        if ($url && $site->getBase() === ($urlParts['host'] ?? '')) {
            $m = [];
            // do we have an id?
            if (preg_match('/(?:^|&)id=([0-9a-z_]+)/', $urlParts['query'], $m)) {
                $isInt = MathUtility::canBeInterpretedAsInteger($m[1]);
                if ($isInt) {
                    $uid = intval($m[1]);
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
     * Get baseURL of the FE
     * force http if UseHttpToFetch is set
     *
     * @return string the baseURL
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getBaseURL(): string
    {
        $baseUrl = BackendDataUtility::getBaseUrl($this->mail->getPage() ?: $this->mail->getPid());

        // if fetching the newsletter using http, set the url to http here
        if (ConfigurationUtility::getExtensionConfiguration('UseHttpToFetch')) {
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getLinkLabel(string $url, string $urlStr, bool $forceFetch = false, string $linkedWord = ''): string
    {
        $baseURL = $this->getBaseURL();
        $label = $linkedWord;
        $contentTitle = '';

        $urlParts = parse_url($url);
        if (!$forceFetch && (str_starts_with($url, $baseURL))) {
            if (($urlParts['fragment'] ?? false) && (str_starts_with($urlParts['fragment'], 'c'))) {
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
            if (empty($urlParts['host']) && (!str_starts_with($url, $baseURL))) {
                // it's internal
                $url = $baseURL . $url;
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
        if ($pageTSConfiguration['showContentTitle'] ?? false) {
            $label = $contentTitle;
        }

        if ($pageTSConfiguration['prependContentTitle'] ?? false) {
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
}
