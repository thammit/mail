<?php

namespace MEDIAESSENZ\Mail\Middleware;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

/**
 * JumpUrl processing hook on TYPO3\CMS\Frontend\Http\RequestHandler
 *
 * @package    TYPO3
 * @subpackage    tx_mail
 */
class JumpurlMiddleware implements MiddlewareInterface
{

    public const RECIPIENT_TABLE_TTADDRESS = 'tt_address';
    public const RECIPIENT_TABLE_FEUSER = 'fe_users';

    protected int $responseType = 0;

    protected string $recipientSourceIdentifier = '';

    protected array $recipientRecord = [];

    protected ServerRequestInterface $request;

    protected ?Mail $mail;

    protected array $siteConfiguration = [];

    /**
     * This is a preprocessor for the actual jumpurl extension to allow counting of clicked links
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Exception|Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;
        $queryParamsToPass = $request->getQueryParams();

        if ($this->shouldProcess()) {
            /** @var Site $site */
            $site = $request->getAttribute('site');
            if ($site instanceof Site) {
                $this->siteConfiguration = $site->getConfiguration()['mail'] ?? [];
            }
            $mailUid = (int)$this->request->getQueryParams()['mail'];
            $submittedRecipient = $this->request->getQueryParams()['rid'] ?? '';
            $submittedAuthCode = $this->request->getQueryParams()['aC'];
            $jumpUrl = $this->request->getQueryParams()['jumpurl'];
            $this->mail = GeneralUtility::makeInstance(MailRepository::class)->findByUid($mailUid);

            $urlId = 0;
            if (MathUtility::canBeInterpretedAsInteger($jumpUrl) && $this->mail instanceof Mail) {
                $urlId = (int)$jumpUrl;
                $this->initRecipientRecord($submittedRecipient);
                $jumpUrl = $this->getTargetUrl($jumpUrl);

                // try to build the ready-to-use target url
                if (!empty($this->recipientRecord)) {
                    $this->validateAuthCode($submittedAuthCode);
                    $jumpUrl = $this->substituteMarkersFromTargetUrl($jumpUrl);
                }
                // jumpUrl generation failed. Early exit here
                if (empty($jumpUrl)) {
                    die('Error: No further link. Please report error to the mail sender.');
                }

            } else {
                // jumpUrl is not an integer -- then this is a URL, that means that the "mail ping"
                // functionality was used to count the number of "opened mails" received (url, mail ping)

                if ($this->isAllowedJumpUrlTarget($jumpUrl)) {
                    $this->responseType = ResponseType::PING;
                }

                // to count the mail ping correctly, we need something unique
                $recipientUid = $submittedAuthCode;
            }

            if ($this->responseType !== ResponseType::ALL) {
                $mailLogParams = [
                    'mail' => $mailUid,
                    'tstamp' => time(),
                    'url' => $jumpUrl,
                    'response_type' => $this->responseType,
                    'url_id' => $urlId,
                    'recipient_source' => $this->recipientSourceIdentifier,
                    'recipient_uid' => (int)($recipientUid ?? $this->recipientRecord['uid']),
                ];
                if ($this->hasRecentLog($mailLogParams) === false) {
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable('tx_mail_domain_model_log')
                        ->insert('tx_mail_domain_model_log', $mailLogParams);
                }
            }
        }

        // finally - finish preprocessing of the jumpurl params
        if (!empty($jumpUrl)) {
            $queryParamsToPass['juHash'] = $this->calculateJumpUrlHash($jumpUrl);
            $queryParamsToPass['jumpurl'] = $jumpUrl;
        }

        return $handler->handle($request->withQueryParams($queryParamsToPass));
    }

    /**
     * Check if an entry exists that is younger than 10 seconds
     *
     * @param array $mailLogParameters
     * @return bool
     * @throws DBALException
     * @throws Exception
     */
    protected function hasRecentLog(array $mailLogParameters): bool
    {
        $logTable = 'tx_mail_domain_model_log';
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($logTable);
        $query = $queryBuilder
            ->count('*')
            ->from($logTable)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailLogParameters['mail'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($mailLogParameters['url'])),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($mailLogParameters['response_type'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url_id', $queryBuilder->createNamedParameter($mailLogParameters['url_id'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_source', $queryBuilder->createNamedParameter($mailLogParameters['recipient_source'])),
                $queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($mailLogParameters['recipient_uid'], PDO::PARAM_INT)),
                $queryBuilder->expr()->lte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp'], PDO::PARAM_INT)),
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp'] - 10, PDO::PARAM_INT))
            );

        $existingLog = $query->execute()->fetchOne();

        return (int)$existingLog > 0;
    }

    /**
     * Returns record no matter what - except if record is deleted
     *
     * @param string $table The table name to search
     * @param int $uid The uid to look up in $table
     * @param string $fields The fields to select, default is "*"
     *
     * @return int|array Returns array (the record) if found, otherwise blank/0 (zero)
     * @throws DBALException|Exception
     * @see getPage_noCheck()
     */
    public function getRawRecord(string $table, int $uid, string $fields = '*'): int|array
    {
        if ($uid > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $res = $queryBuilder->select($fields)
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0))
                )
                ->execute();

            $row = $res->fetchAllAssociative();

            if ($row) {
                if (is_array($row[0])) {
                    return $row[0];
                }
            }
        }
        return 0;
    }

    /**
     * Returns true of the conditions are met to process this middleware
     *
     * @return bool
     */
    protected function shouldProcess(): bool
    {
        return (bool)($this->request->getQueryParams()['mail'] ?? false);
    }

    /**
     * Fetches the target url from the mail record
     *
     * @param int $targetIndex
     * @return string|null
     */
    protected function getTargetUrl(int $targetIndex): ?string
    {
        if ($targetIndex >= 0) {
            $this->responseType = ResponseType::HTML;
            $targetUrl = $this->mail->getHtmlLinks()[$targetIndex]['absRef'] ?? null;
        } else {
            $this->responseType = ResponseType::PLAIN;
            $targetUrl = $this->mail->getPlainLinks()[abs($targetIndex)] ?? null;
        }

        return htmlspecialchars_decode(urldecode($targetUrl));
    }

    /**
     * Will split the combined recipient parameter into the table and uid and fetches the record if successful.
     *
     * @param string $combinedRecipient eg. "fe_users-13667".
     * @throws DBALException|Exception
     * @throws InvalidQueryException
     */
    protected function initRecipientRecord(string $combinedRecipient): void
    {
        // this will split up the "rid=fe_users-13667", where the first part
        // is the DB table name and the second part the UID of the record in the DB table
        $recipientUid = '';
        if (!empty($combinedRecipient)) {
            [$this->recipientSourceIdentifier, $recipientUid] = explode('-', $combinedRecipient);
        }

        // todo get recipient source configuration from site configuration
        $recipientSourceConfiguration = $this->siteConfiguration['recipientSources'][$this->recipientSourceIdentifier] ?? false;

        if ($recipientSourceConfiguration) {
            $recipientService = GeneralUtility::makeInstance(RecipientService::class);
            $recipientService->init($this->siteConfiguration);
            $isSimpleList = $this->recipientSourceIdentifier === 'tx_mail_domain_model_group';
            if ($isSimpleList) {
                $this->recipientRecord['uid'] = $recipientUid;
            } else {
                if ($recipientSourceConfiguration['model'] ?? false) {
                    $recipientData = $recipientService->getRecipientsDataByUidListAndModelName([$recipientUid], $recipientSourceConfiguration['model'], []);
                } else {
                    $recipientData = $recipientService->getRecipientsDataByUidListAndTable([$recipientUid], $this->recipientSourceIdentifier);
                }
                $this->recipientRecord = reset($recipientData);

                // PSR-14 event dispatcher to manipulate recipient data the same way done in mailerService::sendSingleMailAndAddLogEntry method
                $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
                $this->recipientRecord = $eventDispatcher->dispatch(new ManipulateRecipientEvent($this->recipientRecord, $this->recipientSourceIdentifier, $recipientSourceConfiguration))->getRecipientData();
            }
        }
    }

    /**
     * check if the supplied auth code is identical with the counted authCode
     *
     * @param string $submittedAuthCode
     * @throws \Exception
     */
    protected function validateAuthCode(string $submittedAuthCode): void
    {
        $authCodeToMatch = RecipientUtility::stdAuthCode(
            $this->recipientRecord,
            ($this->mail->getAuthCodeFields() ?: 'uid')
        );

        if (!empty($submittedAuthCode) && $submittedAuthCode !== $authCodeToMatch) {
            throw new \Exception(
                'authCode verification failed.',
                1376899631
            );
        }
    }

    /**
     * wrapper function for multiple substitution methods
     *
     * @param string $targetUrl
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function substituteMarkersFromTargetUrl(string $targetUrl): string
    {
        $targetUrl = $this->substituteUserMarkersFromTargetUrl($targetUrl);
        $targetUrl = $this->substituteSystemMarkersFromTargetUrl($targetUrl);

        return str_replace('#', '%23', $targetUrl);
    }

    /**
     * Substitutes ###USER_*### markers in url
     *
     * @param string $targetUrl
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function substituteUserMarkersFromTargetUrl(string $targetUrl): string
    {
        $recipientFields = GeneralUtility::trimExplode(',', ConfigurationUtility::getExtensionConfiguration('defaultRecipientFields'), true);
        if ($additionalRecipientFields = ConfigurationUtility::getExtensionConfiguration('additionalRecipientFields')) {
            $recipientFields = array_merge($recipientFields, GeneralUtility::trimExplode(',', $additionalRecipientFields, true));
        }

        $processedTargetUrl = $targetUrl;
        foreach ($recipientFields as $recipientField) {
            $processedTargetUrl = str_replace(
                '###USER_' . $recipientField . '###',
                $this->recipientRecord[$recipientField] ?? '',
                $processedTargetUrl
            );
        }

        return $processedTargetUrl;
    }

    /**
     * @param string $targetUrl
     * @return string
     */
    protected function substituteSystemMarkersFromTargetUrl(string $targetUrl): string
    {
        $mailId = $this->request->getQueryParams()['mail'];
        $submittedAuthCode = $this->request->getQueryParams()['aC'];

        // substitute system markers
        $markers = ['###MAIL_RECIPIENT_SOURCE###', '###MAIL_ID###', '###MAIL_AUTHCODE###'];
        $substitutions = [
            mb_substr($this->recipientSourceIdentifier, 0, 1),
            $mailId,
            $submittedAuthCode,
        ];
        return str_replace($markers, $substitutions, $targetUrl);
    }

    /**
     * Calculates the verification hash for the jumpUrl extension
     *
     * @param string $targetUrl
     *
     * @return string
     */
    protected function calculateJumpUrlHash(string $targetUrl): string
    {
        return GeneralUtility::hmac($targetUrl, 'jumpurl');
    }

    /**
     * Checks if the target is allowed to be given to jumpurl
     *
     * @param string $target
     * @return bool
     *
     * @throws \Exception
     */
    protected function isAllowedJumpUrlTarget(string $target): bool
    {
        $allowed = false;

        // Check if jumpurl is a valid link to a "dmailerping.gif"
        // Make $checkPath an absolute path pointing to dmailerping.gif, so it can get checked via ::isAllowedAbsPath()
        $checkPath = Environment::getPublicPath() . '/' . ltrim($target, '/');

        // Now check if $checkPath is a valid path and points to a "/dmailerping.gif"
        if (preg_match('#/dmailerping\\.(gif|png)$#', $checkPath) && (GeneralUtility::isAllowedAbsPath($checkPath) || GeneralUtility::isValidUrl($target))) {
            // set juHash as done for external_url in core: http://forge.typo3.org/issues/46071
            $allowed = true;
        } else {
            if (GeneralUtility::isValidUrl($target)) {
                // if it's a valid URL, throw exception
                throw new \Exception('mail: Invalid target.', 1578347190);
            }
        }

        return $allowed;
    }

}
