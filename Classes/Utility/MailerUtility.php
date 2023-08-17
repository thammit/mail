<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use DOMElement;
use DOMXPath;
use Exception;
use Masterminds\HTML5;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Exception\FetchContentFailedException;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;

class MailerUtility
{
    /*
     * CONTENT FETCHING
     */

    /**
     * @param string $url
     * @param string $username
     * @param string $password
     * @return string
     * @throws FetchContentFailedException
     */
    public static function fetchContentFromUrl(string $url, string $username = '', string $password = ''): string
    {
        $url = self::addUsernameAndPasswordToUrl($url, $username, $password);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        try {
            $response = $requestFactory->request($url);
        } catch (Exception $exception) {
            throw new FetchContentFailedException($exception->getMessage(), 1690448922, $exception);
        }
        return self::removeDoubleBrTags($response->getBody()->getContents());
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
     * @param string $mailContent
     * @return string
     */
    public static function getMailBody(string $mailContent): string
    {
        $html = new HTML5();
        $domDocument = $html->loadHTML($mailContent);
        /** @var DOMElement $bodyElement */
        $bodyElement = $domDocument->getElementsByTagName('body');

        return self::removeDoubleBrTags(str_replace('</body>', '</div>', str_replace('<body', '<div', $html->saveHTML($bodyElement))));
    }

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

    public static function removeDoubleBrTags($mailContent): string
    {
        return str_replace('</br>', '', $mailContent);
    }

    /**
     * @param string $mailContent
     * @param array $tags
     * @return string
     */
    public static function removeTags(string $mailContent, array $tags): string
    {
        $html = new HTML5();
        $domDocument = $html->loadHTML($mailContent);
        foreach ($tags as $tag) {
            $tagNodes = $domDocument->getElementsByTagName($tag);
            $remove = [];
            foreach($tagNodes as $node)
            {
                $remove[] = $node;
            }

            foreach ($remove as $item)
            {
                $item->parentNode->removeChild($item);
            }
        }

        return self::removeDoubleBrTags($domDocument->saveHTML());
    }

    /**
     * @param string $mailContent
     * @return string
     */
    public static function removeClassAttributes(string $mailContent): string
    {
        $html = new HTML5();
        $domDocument = $html->loadHTML($mailContent);
        $xpath = new DOMXPath($domDocument);
        $nodes = $xpath->query("//@*[local-name() != 'class']");
        foreach ($nodes as $node) {
            $node->parentNode->removeAttribute('class');
        }

        return self::removeDoubleBrTags($domDocument->saveHTML());
    }

    /**
     * @param string $mailContent
     * @param string $htmlUrl
     * @return string
     */
    public static function makeImageSourcesAbsolute(string $mailContent, string $htmlUrl = ''): string
    {
        $html = new HTML5();
        $domDocument = $html->loadHTML($mailContent);
        $imageNodes = $domDocument->getElementsByTagName('img');
        /** @var DOMElement $imageNode */
        foreach ($imageNodes as $imageNode) {
            if ($imageNode->hasAttribute('src')) {
                $src = $imageNode->getAttribute('src');
                if (!str_starts_with($src, 'http')) {
                    if (str_starts_with($src, '/')) {
                        $baseUrl = parse_url($htmlUrl, PHP_URL_SCHEME) . '://' . parse_url($htmlUrl, PHP_URL_HOST);
                        $src = $baseUrl . $src;
                    } else {
                        $src = rtrim($htmlUrl, '/') . '/' . $src;
                    }
                    $imageNode->setAttribute('src', $src);
                }
            }
        }

        return self::removeDoubleBrTags($domDocument->saveHTML());
    }

    /**
     * @throws ParseException
     */
    public static function addInlineStyles(string $mailContent, string $htmlUrl = ''): string
    {
        $html = new HTML5();
        $domDocument = $html->loadHTML($mailContent);
        $linkNodes = $domDocument->getElementsByTagName('link');
        $stylesheetUrls = [];
        /** @var DOMElement $linkNode */
        foreach ($linkNodes as $linkNode) {
            if ($linkNode->hasAttribute('rel') && $linkNode->getAttribute('rel') === 'stylesheet') {
                $href = $linkNode->getAttribute('href');
                if (!str_starts_with($href, 'http')) {
                    if (str_starts_with($href, '/')) {
                        $baseUrl = parse_url($htmlUrl, PHP_URL_SCHEME) . '://' . parse_url($htmlUrl, PHP_URL_HOST);
                        $href = $baseUrl . $href;
                    } else {
                        $href = rtrim($htmlUrl, '/') . '/' . $href;
                    }
                }
                try {
                    $stylesheetUrls[] = self::fetchContentFromUrl($href);
                } catch (FetchContentFailedException $exception) {
                    ViewUtility::addNotificationError(
                        sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchContentFromUrlError.message'), $href, $exception->getMessage()),
                        LanguageUtility::getLL('general.notification.severity.error.title'));
                }
            }
        }

        return EmogrifierUtility::emogrify($mailContent, implode("\n", $stylesheetUrls), false);
    }

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
     * @param Mail $mail
     * @param string $sourceHost
     * @return string Processed message content
     */
    public static function shortUrlsInPlainText(string $message, Mail $mail, string $sourceHost = '*'): string
    {
        $lengthLimit = $mail->isRedirectAll() ? 0 : 76;
        $mailUid = $mail->getUid() ?? 0;
        $baseUrl = $mail->getRedirectUrl();
        $messageWithReplacedLinks = preg_replace_callback(
            '/(http|https):\\/\\/.+(?=[].?]*([! \'"()<>]+|$))/iU',
            function (array $matches) use ($mailUid, $lengthLimit, $baseUrl, $sourceHost) {
                return $baseUrl . self::createRedirect($matches[0], $lengthLimit, $sourceHost, $mailUid);
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
    public static function createRedirect(string $targetLink, int $lengthLimit, string $sourceHost, int $mailUid): string
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

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            // add own creation type and mail uid to filter and delete old mail redirect
            $record['creation_type'] = (int)(ConfigurationUtility::getExtensionConfiguration('mailRedirectCreationTypeNumber') ?? Constants::DEFAULT_MAIL_REDIRECT_CREATION_TYPE);
            $record['description'] = 'tx_domain_model_mail:' . $mailUid;
        }

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
     * @param string $username
     * @param string $password
     * @return string The new URL with username and password
     */
    public static function addUsernameAndPasswordToUrl(string $url, string $username = '', string $password = ''): string
    {
        if (!$username || !$password) {
            return $url;
        }

        $matches = [];
        if (preg_match('/^https?:\/\//', $url, $matches)) {
            $url = $matches[0] . $username . ':' . $password . '@' . substr($url, strlen($matches[0]));
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
        return $addr['scheme'] . '://' . $addr['host'] . (isset($addr['port']) ? ':' . $addr['port'] : '') . '/' . $ref;
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

    public static function removeDuplicateValues($array) {
        foreach ($array as &$subArray) {
            $subArray = array_values(array_unique($subArray));
        }
        return $array;
    }

}
