<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\Exception;
use JetBrains\PhpStorm\NoReturn;
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
use MEDIAESSENZ\Mail\Utility\ReportUtility;
use Psr\Http\Message\ResponseInterface;
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
    )
    {
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws Exception
     * @throws SiteNotFoundException
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
            'deliveryProgress' => $this->mail->getDeliveryProgress(),
            'numberOfRecipients' => $this->mail->getNumberOfRecipients(),
            'totalSent' => $totalSent,
            'numberOfRecipientsHandled' => $this->mail->getNumberOfRecipientsHandled(),
            'failedResponses' => $failedResponses,
            'returned' => ReportUtility::showWithPercent($failedResponses, $totalSent),
            'returnedPercent' => number_format(($failedResponses / $this->mail->getNumberOfRecipientsHandled() * 100), 2),
            'htmlResponses' => $htmlResponses,
            'plainResponses' => $plainResponses,
            'totalResponses' => $totalResponses,
            'uniquePingResponses' => $uniquePingResponses,
            'viewedPercent' => number_format(($uniquePingResponses / $this->mail->getNumberOfRecipientsHandled() * 100), 2),
            'htmlViewed' => ReportUtility::showWithPercent($uniquePingResponses, $htmlSent),
            'uniqueResponsesTotal' => ReportUtility::showWithPercent($uniqueResponsesTotal, $totalSent),
            'uniqueResponsesHtml' => ReportUtility::showWithPercent($uniqueHtmlResponses, $htmlSent),
            'uniqueResponsesPlain' => ReportUtility::showWithPercent($uniquePlainResponses, $plainSent),
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
     * @throws \Doctrine\DBAL\Driver\Exception
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
     * @return array
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws SiteNotFoundException
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

            /*
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
            $htmlLinks = [];
            // Get all links
            foreach ($dom->getElementsByTagName('a') as $node) {
                $htmlLinks[] = $node;
            }

            // Process all links found
            foreach ($htmlLinks as $link) {
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
            */
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
            $address->setActive(false);
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
            $frontendUser->setActive(false);
            $this->addressRepository->update($frontendUser);
        }

        return count($frontendUsers);
    }
}
