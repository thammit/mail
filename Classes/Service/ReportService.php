<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Exception;
use JsonException;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\ReportUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ReportService
{
    /**
     * @var Mail|null
     */
    protected ?Mail $mail;

    protected array $responseTypesTable = [];
    protected array $recipientSources = [];
    /**
     * @var array
     */
    private array $pageTSConfiguration = [];

    public function __construct(
        protected LogRepository          $logRepository,
        protected AddressRepository      $addressRepository,
        protected FrontendUserRepository $frontendUserRepository,
        protected SiteFinder             $siteFinder,
        protected RecipientService       $recipientService,
        protected EventDispatcherInterface $eventDispatcher
    )
    {
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function init(Mail $mail): void
    {
        $this->mail = $mail;
        $this->recipientSources = $this->siteFinder->getSiteByPageId($this->mail->getPid())->getConfiguration()['mail']['recipientSources'] ?? ConfigurationUtility::getDefaultRecipientSources() ?? [];
        $this->recipientService->init($this->recipientSources);
        $this->responseTypesTable = $this->logRepository->findResponseTypesByMail($this->mail->getUid());
        $this->pageTSConfiguration = BackendUtility::getPagesTSconfig($this->mail->getPid())['mod.']['web_modules.']['mail.'] ?? [];
    }

    /**
     *
     * @return array
     * @throws Exception
     */
    public function getPerformanceData(): array
    {
        $formatSent = $this->logRepository->findFormatSentByMail($this->mail->getUid());

        $htmlSent = (int)($formatSent[SendFormat::HTML] ?? 0) + (int)($formatSent[SendFormat::BOTH] ?? 0);
        $plainSent = (int)($formatSent[SendFormat::PLAIN] ?? 0);
        $totalSent = $htmlSent + $plainSent;

        $uniquePingResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::PING);

        $htmlResponses = (int)($this->responseTypesTable[ResponseType::HTML] ?? 0);
        $plainResponses = (int)($this->responseTypesTable[ResponseType::PLAIN] ?? 0);
        $failedResponses = (int)($this->responseTypesTable[ResponseType::FAILED] ?? 0);
        $totalResponses = $htmlResponses + $plainResponses;

        $uniqueHtmlResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::HTML);
        $uniquePlainResponses = $this->logRepository->countByMailAndResponseType($this->mail->getUid(), ResponseType::PLAIN);
        $uniqueResponsesTotal = $uniqueHtmlResponses + $uniquePlainResponses;

        return [
            'htmlSent' => $htmlSent,
            'htmlSentPercent' => number_format(($htmlSent / $this->mail->getNumberOfRecipients() * 100), 2),
            'plainSent' => $plainSent,
            'plainSentPercent' => number_format(($plainSent / $this->mail->getNumberOfRecipients() * 100), 2),
            'totalSent' => $totalSent,
            'totalSentPercent' => number_format(($totalSent / $this->mail->getNumberOfRecipients() * 100), 2),
            'failedResponses' => $failedResponses,
            'failedResponsesPercent' => number_format(($failedResponses / $totalSent * 100), 2),
            'uniquePingResponses' => $uniquePingResponses,
            'htmlViewedPercent' => $htmlSent ? number_format(($uniquePingResponses / $htmlSent * 100), 2) : 0.0,
            'uniqueResponsesTotal' => $uniqueResponsesTotal,
            'uniqueResponsesTotalPercent' => number_format(($uniqueResponsesTotal / $totalSent * 100), 2),
            'uniqueResponsesHtml' => $uniqueHtmlResponses,
            'uniqueResponsesHtmlPercent' => $htmlSent ? number_format(($uniqueHtmlResponses / $htmlSent * 100), 2) : 0.0,
            'uniqueResponsesPlain' => $uniquePlainResponses,
            'uniqueResponsesPlainPercent' => $plainSent ? number_format(($uniquePlainResponses / $plainSent * 100), 2) : 0.0,
            'totalResponsesVsUniqueResponses' => $uniqueResponsesTotal ? number_format($totalResponses / $uniqueResponsesTotal, 2) : '-',
            'htmlResponsesVsUniqueResponses' => $uniqueHtmlResponses ? number_format($htmlResponses / $uniqueHtmlResponses, 2) : '-',
            'plainResponsesVsUniqueResponses' => $uniquePlainResponses ? number_format($plainResponses / $uniquePlainResponses, 2) : '-',
        ];
    }

    /**
     * @throws Exception
     */
    public function getReturnedData(): array
    {
        $responsesFailed = (int)($this->responseTypesTable['-127'] ?? 0);
        $returnCodesTable = $this->logRepository->findReturnCodesByMail($this->mail->getUid());

        return [
            'total' => number_format($responsesFailed),
            'unknown' => ReportUtility::showWithPercent(($returnCodesTable['550'] ?? 0) + ($returnCodesTable['553'] ?? 0),
                $responsesFailed),
            'full' => ReportUtility::showWithPercent(($returnCodesTable['551'] ?? 0), $responsesFailed),
            'badHost' => ReportUtility::showWithPercent(($returnCodesTable['552'] ?? 0), $responsesFailed),
            'headerError' => ReportUtility::showWithPercent(($returnCodesTable['554'] ?? 0), $responsesFailed),
            'reasonUnknown' => ReportUtility::showWithPercent(($returnCodesTable['-1'] ?? 0), $responsesFailed),
        ];
    }

    /**
     * @param array $returnCodes
     * @return array
     * @throws Exception
     * @throws Exception
     * @throws InvalidQueryException
     */
    public function getReturnedDetailsData(array $returnCodes = []): array
    {
        $data = [];
        $failedRecipientIds = $this->logRepository->findFailedRecipientIdsByMailAndReturnCodeGroupedByRecipientSource($this->mail->getUid(), $returnCodes);
        foreach ($failedRecipientIds as $recipientSourceIdentifier => $recipientIds) {
            if ($recipientSourceIdentifier === 'tx_mail_domain_model_group') {
                $data[$recipientSourceIdentifier] = $recipientIds;
            } else {
                // get site configuration
                $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier];
                if ($recipientSourceConfiguration['model'] ?? false) {
                    $data[$recipientSourceIdentifier] = $this->recipientService->getRecipientsDataByUidListAndModelName($recipientIds, $recipientSourceConfiguration['model']);
                } else {
                    $data[$recipientSourceIdentifier] = $this->recipientService->getRecipientsDataByUidListAndTable($recipientIds, $recipientSourceIdentifier);
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws SiteNotFoundException
     * @throws JsonException
     */
    public function getResponsesData(): array
    {
        $mostPopularHtmlLinks = $this->logRepository->findMostPopularLinksByMailAndResponseType($this->mail->getUid());
        $mostPopularPlainLinks = $this->logRepository->findMostPopularLinksByMailAndResponseType($this->mail->getUid(), ResponseType::PLAIN);
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
        if ($htmlLinks = $this->mail->getHtmlLinks()) {
            foreach ($htmlLinks as $htmlLinkId => $htmlLink) {
                // convert &amp; of query params back
                $urlArr[$htmlLinkId] = html_entity_decode($htmlLink['absRef']);
                $urlMd5Map[md5($htmlLink['absRef'])] = $htmlLinkId;
            }
        }

        if ($plainLinks = $this->mail->getPlainLinks()) {
            foreach ($plainLinks as $plainLinkId => $plainLink) {
                $urlArr[-$plainLinkId] = $plainLink;
            }
        }

        $mappedPlainUrlsTable = [];
        foreach ($mostPopularPlainLinks as $id => $counter) {
            $url = $urlArr[(int)$id];
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
            $htmlLinks = $this->mail->getHtmlLinks();
        }

        $data = [];
        $html = false;

        $clickedLinks = array_keys($urlCounter['total']);

        foreach ($clickedLinks as $id) {
            // $id is the jumpurl ID
            $origId = $id;
            $id = abs((int)$id);
            $url = $htmlLinks[$id]['ref'] ?: $urlArr[$origId];
            $urlStr = ReportUtility::getUrlStr($url, $this->mail->getPid());
            $label = $urlStr;
            if ($this->pageTSConfiguration['showContentTitle'] ?? false) {
                $label = ReportUtility::getContentTitle($url, $this->getBaseURL());
                if (($this->pageTSConfiguration['prependContentTitle'] ?? false) && $label !== $url) {
                    $label .= ' (' . $url . ')';
                }

            }

            if (isset($urlCounter['html'][$id]['plainId'])) {
                $data[] = [
                    'label' => $label,
                    'iconIdentifier' => ReportUtility::determineDocumentIconIdentifier($url),
                    'title' => $htmlLinks[$id]['title'],
                    'totalCounter' => $urlCounter['total'][$origId]['counter'],
                    'htmlCounter' => $urlCounter['html'][$id]['counter'],
                    'plainCounter' => $urlCounter['html'][$id]['plainCounter'],
                    'url' => $urlStr,
                ];
            } else {
                $html = !empty($urlCounter['html'][$id]['counter']);
                $data[] = [
                    'label' => $label,
                    'iconIdentifier' => ReportUtility::determineDocumentIconIdentifier($url),
                    'title' => $htmlLinks[$id]['title'] ?? $htmlLinks[$id]['ref'],
                    'totalCounter' => ($html ? $urlCounter['html'][$id]['counter'] ?? 0 : $urlCounter['plain'][$origId]['counter'] ?? 0),
                    'htmlCounter' => $urlCounter['html'][$id]['counter'] ?? 0,
                    'plainCounter' => $urlCounter['plain'][$origId]['counter'] ?? 0,
                    'url' => $urlStr,
                ];
            }
        }

        // go through all links that were not clicked yet and that have a label
        if (isset($htmlLinks['id'])) {
            foreach ($urlArr as $id => $link) {
                if (!in_array($id, $clickedLinks)) {
                    // a link to this host?
                    $label = $htmlLinks[$id]['label'] . ' (' . (ReportUtility::getUrlStr($link, $this->mail->getPid()) ?: '/') . ')';
                    $data[] = [
                        'label' => $label,
                        'iconIdentifier' => ReportUtility::determineDocumentIconIdentifier($link),
                        'title' => $htmlLinks[$id]['title'] ?? $htmlLinks[$id]['ref'],
                        'totalCounter' => ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
                        'htmlCounter' => $urlCounter['html'][$id]['counter'],
                        'plainCounter' => $urlCounter['plain'][$id]['counter'],
                        'url' => $link,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Get baseURL of the FE
     * force http if useHttpToFetch is set
     *
     * @return string the baseURL
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getBaseURL(): string
    {
        $baseUrl = BackendDataUtility::getBaseUrl($this->mail->getPage() ?: $this->mail->getPid());

        // if fetching the newsletter using http, set the url to http here
        if (ConfigurationUtility::getExtensionConfiguration('useHttpToFetch')) {
            $baseUrl = str_replace('https', 'http', $baseUrl);
        }

        return $baseUrl;
    }

}
