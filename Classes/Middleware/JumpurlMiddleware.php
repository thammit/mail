<?php

namespace MEDIAESSENZ\Mail\Middleware;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

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

    public const RESPONSE_TYPE_URL = -1;
    public const RESPONSE_TYPE_HREF = 1;
    public const RESPONSE_TYPE_PLAIN = 2;

    /**
     * @var int
     */
    protected int $responseType = 0;

    /**
     * @var string
     */
    protected string $recipientTable = '';

    /**
     * @var array
     */
    protected array $recipientRecord = [];

    /**
     * @var ServerRequestInterface
     */
    protected ServerRequestInterface $request;

    /**
     * @var array
     */
    protected array $directMailRecord;

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
            $mailId = $this->request->getQueryParams()['mid'];
            $submittedRecipient = $this->request->getQueryParams()['rid'];
            $submittedAuthCode = $this->request->getQueryParams()['aC'];
            $jumpurl = $this->request->getQueryParams()['jumpurl'];

            $urlId = 0;
            if (MathUtility::canBeInterpretedAsInteger($jumpurl)) {
                $urlId = $jumpurl;
                $this->initDirectMailRecord($mailId);
                $this->initRecipientRecord($submittedRecipient);
                $jumpurl = $this->getTargetUrl($jumpurl);

                // try to build the ready-to-use target url
                if (!empty($this->recipientRecord)) {
                    $this->validateAuthCode($submittedAuthCode);
                    $jumpurl = $this->substituteMarkersFromTargetUrl($jumpurl);

                    $this->performFeUserAutoLogin();
                }
                // jumpUrl generation failed. Early exit here
                if (empty($jumpurl)) {
                    die('Error: No further link. Please report error to the mail sender.');
                }

            } else {
                // jumpUrl is not an integer -- then this is a URL, that means that the "dmailerping"
                // functionality was used to count the number of "opened mails" received (url, dmailerping)

                if ($this->isAllowedJumpUrlTarget($jumpurl)) {
                    $this->responseType = self::RESPONSE_TYPE_URL;
                }

                // to count the dmailerping correctly, we need something unique
                $recipientUid = $submittedAuthCode;
            }

            if ($this->responseType !== 0) {
                $mailLogParams = [
                    'mid' => (int)$mailId,
                    'tstamp' => time(),
                    'url' => $jumpurl,
                    'response_type' => $this->responseType,
                    'url_id' => (int)$urlId,
                    'rtbl' => mb_substr($this->recipientTable, 0, 1),
                    'rid' => (int)($recipientUid ?? $this->recipientRecord['uid']),
                ];
                if ($this->hasRecentLog($mailLogParams) === false) {
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable('sys_dmail_maillog')
                        ->insert('sys_dmail_maillog', $mailLogParams);
                }
            }
        }

        // finally - finish preprocessing of the jumpurl params
        if (!empty($jumpurl)) {
            $queryParamsToPass['juHash'] = $this->calculateJumpUrlHash($jumpurl);
            $queryParamsToPass['jumpurl'] = $jumpurl;
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
        $logTable = 'sys_dmail_maillog';
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($logTable);
        $query = $queryBuilder
            ->count('*')
            ->from($logTable)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mailLogParameters['mid'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($mailLogParameters['url'])),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($mailLogParameters['response_type'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url_id', $queryBuilder->createNamedParameter($mailLogParameters['url_id'], PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($mailLogParameters['rtbl'])),
                $queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($mailLogParameters['rid'], PDO::PARAM_INT)),
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
        $mid = $this->request->getQueryParams()['mid'] ?? null;
        return ($mid !== null);
    }

    /**
     * Fills $this->directMailRecord with the requested sys_dmail record
     *
     * @param int $mailId
     * @throws DBALException|Exception
     */
    protected function initDirectMailRecord(int $mailId): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail');
        $result = $queryBuilder
            ->select('mailContent', 'page', 'authcode_fieldList')
            ->from('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($mailId, PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAssociative();

        $this->directMailRecord = $result;
    }

    /**
     * Fetches the target url from the direct mail record
     *
     * @param int $targetIndex
     * @return string|null
     */
    protected function getTargetUrl(int $targetIndex): ?string
    {
        $targetUrl = null;

        if (!empty($this->directMailRecord)) {
            $mailContent = unserialize(
                base64_decode($this->directMailRecord['mailContent']),
                ['allowed_classes' => false]
            );
            if ($targetIndex >= 0) {
                // Link (number)
                $this->responseType = self::RESPONSE_TYPE_HREF;
                $targetUrl = $mailContent['html']['hrefs'][$targetIndex]['absRef'];
            } else {
                // Link (number, plaintext)
                $this->responseType = self::RESPONSE_TYPE_PLAIN;
                $targetUrl = $mailContent['plain']['link_ids'][abs($targetIndex)];
            }
            $targetUrl = htmlspecialchars_decode(urldecode($targetUrl));
        }
        return $targetUrl;
    }

    /**
     * Will split the combined recipient parameter into the table and uid and fetches the record if successful.
     *
     * @param string $combinedRecipient eg. "f_13667".
     * @throws DBALException|Exception
     */
    protected function initRecipientRecord(string $combinedRecipient): void
    {
        // this will split up the "rid=f_13667", where the first part
        // is the DB table name and the second part the UID of the record in the DB table
        $recipientTable = '';
        $recipientUid = '';
        if (!empty($combinedRecipient)) {
            [$recipientTable, $recipientUid] = explode('_', $combinedRecipient);
        }

        $this->recipientTable = match ($recipientTable) {
            't' => self::RECIPIENT_TABLE_TTADDRESS,
            'f' => self::RECIPIENT_TABLE_FEUSER,
            default => '',
        };

        if (!empty($this->recipientTable)) {
            $this->recipientRecord = $this->getRawRecord($this->recipientTable, $recipientUid);
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
        $authCodeToMatch = MailerUtility::stdAuthCode(
            $this->recipientRecord,
            ($this->directMailRecord['authcode_fieldList'] ?: 'uid')
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
     */
    protected function substituteUserMarkersFromTargetUrl(string $targetUrl): string
    {
        $rowFieldsArray = explode(
            ',',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['defaultRecipFields']
        );
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
            $rowFieldsArray = array_merge(
                $rowFieldsArray,
                explode(
                    ',',
                    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']
                )
            );
        }

        $processedTargetUrl = $targetUrl;
        foreach ($rowFieldsArray as $substField) {
            $processedTargetUrl = str_replace(
                '###USER_' . $substField . '###',
                $this->recipientRecord[$substField],
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
        $mailId = $this->request->getQueryParams()['mid'];
        $submittedAuthCode = $this->request->getQueryParams()['aC'];

        // substitute system markers
        $markers = ['###SYS_TABLE_NAME###', '###SYS_MAIL_ID###', '###SYS_AUTHCODE###'];
        $substitutions = [
            mb_substr($this->recipientTable, 0, 1),
            $mailId,
            $submittedAuthCode,
        ];
        return str_replace($markers, $substitutions, $targetUrl);
    }

    /**
     * Auto Login an FE User, only possible if we're allowed to set the $_POST variables and
     * in the authcode_fieldlist the field "password" is computed in as well
     *
     * TODO: Is this still valid?
     */
    protected function performFeUserAutoLogin()
    {
        // TODO: add a switch in Direct Mail configuration to decide if this option should be enabled by default
        if ($this->recipientTable === 'fe_users' &&
            GeneralUtility::inList(
                $this->directMailRecord['authcode_fieldList'],
                'password'
            )) {
            $_POST['user'] = $this->recipientRecord['username'];
            $_POST['pass'] = $this->recipientRecord['password'];
            $_POST['pid'] = $this->recipientRecord['pid'];
            $_POST['logintype'] = 'login';
        }
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
        } else if (GeneralUtility::isValidUrl($target)) {
            // if it's a valid URL, throw exception
            throw new \Exception('direct_mail: Invalid target.', 1578347190);
        }

        return $allowed;
    }

}
