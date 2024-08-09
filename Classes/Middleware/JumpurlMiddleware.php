<?php

namespace MEDIAESSENZ\Mail\Middleware;

use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\MailRepository;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
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

    protected array $recipientSources = [];

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
                $this->recipientSources = ConfigurationUtility::getRecipientSources($site->getConfiguration());
            }
            $mailUid = (int)$this->request->getQueryParams()['mail'];
            $submittedRecipient = $this->request->getQueryParams()['rid'] ?? '';
            $submittedAuthCode = $this->request->getQueryParams()['aC'];
            $jumpUrl = $this->request->getQueryParams()['jumpurl'];
            $this->mail = GeneralUtility::makeInstance(MailRepository::class)->findByUid($mailUid);

            $this->initRecipientRecord($submittedRecipient);
            $urlId = 0;
            if ((MathUtility::canBeInterpretedAsInteger($jumpUrl) || $jumpUrl === '-0') && $this->mail instanceof Mail) {
                $this->responseType = $jumpUrl === '-0' || (int)$jumpUrl < 0 ? ResponseType::PLAIN : ResponseType::HTML;
                $urlId = abs((int)$jumpUrl);
                $jumpUrlTargetUrl = $this->getTargetUrl($urlId);

                // try to build the ready-to-use target url
                if (!empty($this->recipientRecord)) {
                    $this->validateAuthCode($submittedAuthCode);
                    $jumpUrlTargetUrl = $this->substituteMarkersFromTargetUrl($jumpUrlTargetUrl);
                }
                // jumpUrl generation failed. Early exit here
                if (empty($jumpUrlTargetUrl)) {
                    die('Error: No further link. Please report error to the mail sender.');
                }

            } else {
                // jumpUrl is not an integer -- then this is a URL, that means that the "mail ping"
                // functionality was used to count the number of "opened mails" received (url, mail ping)

                $jumpUrlTargetUrl = $jumpUrl;

                if ($this->isAllowedJumpUrlTarget($jumpUrlTargetUrl)) {
                    $this->responseType = ResponseType::PING;
                }
            }

            if ($this->responseType !== ResponseType::ALL) {
                $mailLogParams = [
                    'mail' => $mailUid,
                    'tstamp' => time(),
                    'url' => $jumpUrlTargetUrl,
                    'response_type' => $this->responseType,
                    'url_id' => $urlId,
                    'recipient_source' => $this->recipientSourceIdentifier,
                    'recipient_uid' => $this->recipientRecord['uid'] ?? hexdec($submittedAuthCode)
                ];
                if ($this->hasRecentLog($mailLogParams) === false) {
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable('tx_mail_domain_model_log')
                        ->insert('tx_mail_domain_model_log', $mailLogParams);
                }
            }
        }

        // finally - finish preprocessing of the jumpurl params
        if (!empty($jumpUrlTargetUrl)) {
            $queryParamsToPass['juHash'] = $this->calculateJumpUrlHash($jumpUrlTargetUrl);
            $queryParamsToPass['jumpurl'] = $jumpUrlTargetUrl;
        }

        return $handler->handle($request->withQueryParams($queryParamsToPass));
    }

    /**
     * Check if an entry exists that is younger than 10 seconds
     *
     * @param array $mailLogParameters
     * @return bool
     * @throws \Doctrine\DBAL\Exception
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
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailLogParameters['mail'], Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($mailLogParameters['url'])),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($mailLogParameters['response_type'], Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('url_id', $queryBuilder->createNamedParameter($mailLogParameters['url_id'], Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_source', $queryBuilder->createNamedParameter($mailLogParameters['recipient_source'])),
                $queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($mailLogParameters['recipient_uid'], Connection::PARAM_INT)),
                $queryBuilder->expr()->lte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp'], Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp'] - 10, Connection::PARAM_INT))
            );

        $existingLog = $query->executeQuery()->fetchOne();

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
     * @throws \Doctrine\DBAL\Exception
     * @see getPage_noCheck()
     */
    public function getRawRecord(string $table, int $uid, string $fields = '*'): int|array
    {
        if ($uid > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $res = $queryBuilder->select($fields)
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0))
                )
                ->executeQuery();

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
        $targetUrl = $this->mail->getHtmlLinks()[$targetIndex]['absRef'] ?? null;

        return htmlspecialchars_decode(urldecode($targetUrl));
    }

    /**
     * Will split the combined recipient parameter into the table and uid and fetches the record if successful.
     *
     * @param string $combinedRecipient eg. "fe_users-13667".
     * @throws Exception
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function initRecipientRecord(string $combinedRecipient): void
    {
        if (!$combinedRecipient) {
            return;
        }

        // this will split up the "rid=fe_users-13667", where the first part
        // is the DB table name and the second part the UID of the record in the DB table
        [$this->recipientSourceIdentifier, $recipientUid] = explode('-', $combinedRecipient);

        if ($this->recipientSources[$this->recipientSourceIdentifier] ?? false) {
            /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
            $recipientSourceConfiguration = $this->recipientSources[$this->recipientSourceIdentifier];
            $recipientService = GeneralUtility::makeInstance(RecipientService::class);
            $recipientService->init($this->recipientSources);
            $isSimpleList = $this->recipientSourceIdentifier === 'tx_mail_domain_model_group';
            if ($isSimpleList) {
                $this->recipientRecord['uid'] = $recipientUid;
            } else {
                if ($recipientSourceConfiguration->model) {
                    $recipientData = $recipientService->getRecipientsDataByUidListAndModelName([$recipientUid], $recipientSourceConfiguration->model, []);
                } else {
                    $recipientData = $recipientService->getRecipientsDataByUidListAndTable([$recipientUid], $recipientSourceConfiguration->table);
                }
                $this->recipientRecord = reset($recipientData);

                // PSR-14 event dispatcher to manipulate recipient data the same way done in mailerService::sendSingleMailAndAddLogEntry method
                $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
                $this->recipientRecord = $eventDispatcher->dispatch(new ManipulateRecipientEvent($this->recipientRecord, $recipientSourceConfiguration))->getRecipientData();
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
        // Check if jumpurl is a valid link to a "mailerping.gif" or .png
        // Make $checkPath an absolute path pointing to mailerping.gif, so it can get checked via ::isAllowedAbsPath()
        $checkPath = Environment::getPublicPath() . '/' . ltrim($target, '/');

        // Now check if $checkPath is a valid path and points to a "/mailerping.gif"
        if (preg_match('#/mailerping\\.(gif|png)$#', $checkPath) && (GeneralUtility::isAllowedAbsPath($checkPath) || GeneralUtility::isValidUrl($target))) {
            // set juHash as done for external_url in core: http://forge.typo3.org/issues/46071
            return true;
        } else {
            if (GeneralUtility::isValidUrl($target)) {
                // if it's a valid URL, throw exception
                throw new \Exception('mail: Invalid target.', 1578347190);
            }
        }

        return false;
    }

}
