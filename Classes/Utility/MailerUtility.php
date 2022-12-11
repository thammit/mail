<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Exception;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;

class MailerUtility
{
    /*
     * CONTENT FETCHING
     */

    /**
     * @param $url
     * @return string|bool
     * @throws RequestException $exception
     */
    public static function fetchContentFromUrl($url): string|bool
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        try {
            $response = $requestFactory->request($url);
        } catch (Exception) {
            return false;
        }
        return $response->getBody()->getContents();
    }


    /*
     * CONTENT ANALYSING
     */

    /**
     * Returns true if content contains frame tag
     *
     * @param string $content
     * @return bool
     */
    public static function contentContainsFrameTag(string $content): bool
    {
        return str_contains($content, '<frame ');
    }

    /**
     * Check content and return true if any boundary comment is found
     * @param $content
     * @return bool
     */
    public static function contentContainsBoundaries($content): bool
    {
        return str_contains($content, '<!--' . Constants::CONTENT_SECTION_BOUNDARY);
    }

    /*
     * CONTENT PARTS EXTRACTION
     */

    /**
     * @param array $contentParts content parts
     * @param array|null $userCategories category uids of the user
     *
     * @return string Content of the email, which the recipient subscribed or false if no content found
     */
    public static function getContentFromContentPartsMatchingUserCategories(array $contentParts, array $userCategories = null): string
    {
        $returnContentParts = [];
        $mailHasContent = false;
        $boundaryMax = count($contentParts) - 1;
        foreach ($contentParts as $blockKey => $contentPart) {
            if (empty($contentPart[1])) {
                continue;
            }
            $key = substr($contentPart[0], 1);
            // $key can be empty, contain the string "END" or a comma separated list of category uids
            if (empty($key) || $userCategories === null) {
                // content has no category restrictions -> add to output if not empty
                $returnContentParts[] = $contentPart[1];
                $mailHasContent = true;
            } else {
                if ($key === 'END') {
                    $returnContentParts[] = $contentPart[1];
                    // There is content, and it is not just the header and footer content, or it is the only content because we have no mail boundaries.
                    if (!($blockKey === 0 || $blockKey === $boundaryMax) || count($contentParts) === 1) {
                        $mailHasContent = true;
                    }
                } else {
                    $contentCategories = GeneralUtility::intExplode(',', $key, true);
                    if (empty($contentCategories) || count(array_intersect($contentCategories, $userCategories)) > 0) {
                        $returnContentParts[] = $contentPart[1];
                        $mailHasContent = true;
                    }
                }
            }
        }
        return $mailHasContent ? implode('', $returnContentParts) : '';
    }

    /*
     * CONTENT MODIFICATIONS
     */

    /**
     * Removes html comments when outside script and style pairs
     *
     * @param string $content The email content
     *
     * @return string HTML content without comments
     */
    public static function removeHtmlComments(string $content): string
    {
        $content = preg_replace('/\/\*<!\[CDATA\[\*\/[\t\v\n\r\f]*<!--/', '/*<![CDATA[*/', $content);
        $content = preg_replace('/[\t\v\n\r\f]*<!(?:--[^\[<>][\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
        return preg_replace('/\/\*<!\[CDATA\[\*\//', '/*<![CDATA[*/<!--', $content);
    }

    /**
     * Takes a clear-text message body for a plain text email, finds all 'http://' links and if they are longer than 76 chars they are converted to a shorter URL with a hash parameter.
     * The real parameter is stored in the database and the hash-parameter/URL will be redirected to the real parameter when the link is clicked.
     * This function is about preserving long links in messages.
     *
     * @param string $message Message content
     * @param int $lengthLimit Length limit; Default = 76 or 0 for all
     * @param string $baseUrl
     * @param string $sourceHost
     * @return string Processed message content
     */
    public static function shortUrlsInPlainText(string $message, int $lengthLimit, string $baseUrl, string $sourceHost = '*'): string
    {
        $messageWithReplacedLinks = preg_replace_callback(
            '/(http|https):\\/\\/.+(?=[].?]*([! \'"()<>]+|$))/iU',
            function (array $matches) use ($lengthLimit, $baseUrl, $sourceHost) {
                return $baseUrl . static::createRedirect($matches[0], $lengthLimit, $sourceHost);
            },
            $message
        );

        if ($message !== $messageWithReplacedLinks) {
            GeneralUtility::makeInstance(RedirectCacheService::class)->rebuildForHost($sourceHost);
        }

        return $messageWithReplacedLinks;
    }


    /*
     * MIXED HELPERS
     */

    /**
     * @return string The created redirect url
     */
    public static function createRedirect(string $targetLink, int $lengthLimit, string $sourceHost): string
    {
        if (strlen($targetLink) <= $lengthLimit) {
            return $targetLink;
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_redirect');

        $sourcePath = '/redirect-' . substr(md5($targetLink), 0, 20);

        if ($connection->count('*', 'sys_redirect', ['source_path' => $sourcePath, 'source_host' => $sourceHost]) > 0) {
            return $sourcePath;
        }

        $record = [
            'pid' => 0,
            'updatedon' => time(),
            'createdon' => time(),
            'createdby' => 0,
            'deleted' => 0,
            'disabled' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'source_host' => $sourceHost,
            'source_path' => $sourcePath,
            'is_regexp' => 0,
            'force_https' => 0,
            'respect_query_parameters' => 0,
            'target' => $targetLink,
            'target_statuscode' => 307,
            'hitcount' => 0,
            'lasthiton' => 0,
            'disable_hitcount' => 0,
            'protected' => 1,
        ];

        $connection->insert('sys_redirect', $record);
        return $sourcePath;
    }

    /**
     * @param int $mailUid
     * @param string $recipientSourceIdentifier
     * @param int $recipientUid
     * @return string
     */
    public static function buildMailIdentifierHeaderWithoutHash(int $mailUid, string $recipientSourceIdentifier, int $recipientUid): string
    {
        return 'MID' . $mailUid . '-' . $recipientSourceIdentifier . '-' . $recipientUid;
    }

    /**
     * @param string $mailIdentifierHeader
     * @return string
     */
    public static function buildMailIdentifierHeader(string $mailIdentifierHeader): string
    {
        return $mailIdentifierHeader . '-' . md5($mailIdentifierHeader);
    }

    /**
     * @param $rawHeaders
     * @param $header
     * @return array|bool
     */
    public static function decodeMailIdentifierHeader($rawHeaders, $header): array|bool
    {
        if (str_contains($rawHeaders, $header)) {
            $p = explode($header . ':', $rawHeaders, 2);
            $l = explode(LF, $p[1], 2);
            [$mailUid, $recipientSourceIdentifier, $recipientUid, $hash] = GeneralUtility::trimExplode('-', $l[0]);
            $mailUid = (int)ltrim($mailUid, 'MID');
            if (md5(self::buildMailIdentifierHeaderWithoutHash($mailUid, $recipientSourceIdentifier, (int)$recipientUid)) === $hash) {
                return [
                    'mail' => $mailUid,
                    'recipient_source' => $recipientSourceIdentifier,
                    'recipient_uid' => (int)$recipientUid,
                ];
            }
        }
        return false;
    }

    /**
     * Fetches the attachment files referenced in the mail record.
     *
     * @param int $uid The uid of the mail record to fetch the records for
     * @return array An array of FileReferences
     */
    public static function getAttachments(int $uid): array
    {
        return GeneralUtility::makeInstance(FileRepository::class)->findByRelation('tx_mail_domain_model_mail', 'attachment', $uid);
    }

    /**
     * Get the fully-qualified domain name of the host
     * Copy from TYPO3 v9.5, will be removed in TYPO3 v10.0
     *
     * @param bool $requestHost Use request host (when not in CLI mode).
     * @return string The fully-qualified host name.
     */
    public static function generateMessageId(bool $requestHost = true): string
    {
        $host = '';
        // If not called from the command-line, resolve on getIndpEnv()
        if ($requestHost && !Environment::isCli()) {
            $host = GeneralUtility::getIndpEnv('HTTP_HOST');
        }
        if (!$host) {
            // will fail for PHP 4.1 and 4.2
            $host = @php_uname('n');
            // 'n' is ignored in broken installations
            if (strpos($host, ' ')) {
                $host = '';
            }
        }
        // We have not found a FQDN yet
        if ($host && !str_contains($host, '.')) {
            $ip = gethostbyname($host);
            // We got an IP address
            if ($ip != $host) {
                $fqdn = gethostbyaddr($ip);
                if ($ip != $fqdn) {
                    $host = $fqdn;
                }
            }
        }
        if (!$host) {
            $host = 'localhost.localdomain';
        }

        if ($host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
            $host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_',
                    $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
        }

        $idLeft = time() . '.' . uniqid();
        $idRight = $host ?: 'symfony.generated';

        return $idLeft . '@' . $idRight;
    }

    /**
     * @param string $url
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getUrlForExternalPage(string $url): string
    {
        return @parse_url($url, PHP_URL_SCHEME) ? $url : ConfigurationUtility::getDefaultScheme() . '://' . $url;
    }

    /**
     * Add username and password for a password secured page
     * username and password are configured in the configuration module
     *
     * @param string $url The URL
     * @param array $params Parameters from pageTS
     *
     * @return string The new URL with username and password
     */
    public static function addUsernameAndPasswordToUrl(string $url, array $params): string
    {
        $username = $params['httpUsername'] ?? '';
        $password = $params['httpPassword'] ?? '';
        $matches = [];
        if ($username && $password && preg_match('/^https?:\/\//', $url, $matches)) {
            $url = $matches[0] . $username . ':' . $password . '@' . substr($url, strlen($matches[0]));
        }
        if (($params['simulateUsergroup'] ?? false) && MathUtility::canBeInterpretedAsInteger($params['simulateUsergroup'])) {
            $glue = str_contains($url, '?') ? '&' : '?';
            $url .= $glue . 'dmail_fe_group=' . (int)$params['simulateUsergroup'] . '&access_token=' . RegistryUtility::createAndGetAccessToken();
        }
        return $url;
    }

    /**
     * Returns the absolute address of a link
     *
     * @param string $ref Address to use
     * @param string $path
     * @return string The absolute address
     */
    public static function absRef(string $ref, string $path): string
    {
        $ref = trim($ref);
        $info = parse_url($ref);
        if ($info['scheme'] ?? false) {
            return $ref;
        }

        if (preg_match('/^\//', $ref)) {
            // if ref is an absolute link
            $addr = parse_url($path);
            return $addr['scheme'] . '://' . $addr['host'] . (($addr['port'] ?? false) ? ':' . $addr['port'] : '') . $ref;
        }

        // If the reference is relative, the path is added,
        // in order for us to fetch the content
        if (str_ends_with($path, '/')) {
            // if the last char is a /, then prepend the ref
            return $path . $ref;
        }

        // if the last char not a /, then assume it's an absolute
        $addr = parse_url($path);
        return $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . '/' . $ref;
    }

    /**
     * Gets the unix timestamp as milliseconds.
     *
     * @return int The unix timestamp as milliseconds
     */
    public static function getMilliseconds(): int
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * @param int $sent
     * @param int $numberOfRecipients
     * @return int
     */
    public static function calculatePercentOfSend(int $sent, int $numberOfRecipients): int
    {
        $percentOfSent = 100 / $numberOfRecipients * $sent;
        if ($percentOfSent > 100) {
            $percentOfSent = 100;
        }
        if ($percentOfSent < 0) {
            $percentOfSent = 0;
        }
        return (int)$percentOfSent;
    }
}
