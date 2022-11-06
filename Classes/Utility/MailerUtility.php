<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Exception;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
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
     * Extracts all media-links from given content
     *
     * @param string $content
     * @param string $path
     * @return array
     */
    public static function extractMediaLinks(string $content, string $path): array
    {
        $mediaLinks = [];

        $attribRegex = static::tagRegex(['img']);
        $imageList = '';

        // split the document by the beginning of the above tags
        $codeParts = preg_split($attribRegex, $content);
        $len = strlen($codeParts[0]);
        $pieces = count($codeParts);
        $reg = [];
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($content, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codeParts[$i]) + 2;
            preg_match('/[^>]*/', $codeParts[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = static::getTagAttributes($reg[0]);
            $imageData = [];

            // Finds the src or background attribute
            $imageData['ref'] = $attributes['src'] ?? '';
            if ($imageData['ref']) {
                // find out if the value had quotes around it
                $imageData['quotes'] = (substr($codeParts[$i], strpos($codeParts[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting later-on
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if (!str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = static::absRef($imageData['ref'], $path);
                    $imageData['tag'] = $tag;
                    $imageData['use_jumpurl'] = (isset($attributes['dmailerping']) && $attributes['dmailerping']) ? 1 : 0;
                    $imageData['do_not_embed'] = !empty($attributes['do_not_embed']);
                    $mediaLinks[] = $imageData;
                }
            }
        }

        // Extracting stylesheets
        $attribRegex = static::tagRegex(['link']);
        // Split the document by the beginning of the above tags
        $codeParts = preg_split($attribRegex, $content);
        $pieces = count($codeParts);
        for ($i = 1; $i < $pieces; $i++) {
            preg_match('/[^>]*/', $codeParts[$i], $reg);
            // fetches the attributes for the tag
            $attributes = static::getTagAttributes($reg[0]);
            $imageData = [];
            if (strtolower($attributes['rel']) == 'stylesheet' && $attributes['href']) {
                // Finds the src or background attribute
                $imageData['ref'] = $attributes['href'];
                // Finds out if the value had quotes around it
                $imageData['quotes'] = (substr($codeParts[$i], strpos($codeParts[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if (!str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = static::absRef($imageData['ref'], $path);
                    $mediaLinks[] = $imageData;
                }
            }
        }

        // fixes javascript rollovers
        $codeParts = explode('.src', $content);
        $pieces = count($codeParts);
        $expr = '/^[^' . quotemeta('"') . quotemeta("'") . ']*/';
        for ($i = 1; $i < $pieces; $i++) {
            $temp = $codeParts[$i];
            $temp = trim(str_replace('=', '', trim($temp)));
            preg_match($expr, substr($temp, 1, strlen($temp)), $reg);
            $imageData['ref'] = $reg[0];
            $imageData['quotes'] = substr($temp, 0, 1);
            // subst_str is the string to look for, when substituting lateron
            $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
            $theInfo = GeneralUtility::split_fileref($imageData['ref']);

            switch ($theInfo['fileext']) {
                case 'gif':
                    // do like jpg
                case 'jpeg':
                    // do like jpg
                case 'jpg':
                    if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
                        $imageList .= '|' . $imageData['subst_str'] . '|';
                        $imageData['absRef'] = static::absRef($imageData['ref'], $path);
                        $mediaLinks[] = $imageData;
                    }
                    break;
                default:
                    // do nothing
            }
        }

        return $mediaLinks;
    }

    /**
     * Extracts all hyperlinks from given content
     *
     * @param string $content
     * @param string $path
     * @return array
     */
    public static function extractHyperLinks(string $content, string $path): array
    {
        $hyperLinks = [];
        $linkList = '';

        $attribRegex = static::tagRegex(['a', 'form', 'area']);

        // Splits the document by the beginning of the above tags
        $codeParts = preg_split($attribRegex, $content);
        $len = strlen($codeParts[0]);
        $pieces = count($codeParts);
        $reg = [];
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($content, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codeParts[$i]) + 2;
            preg_match('/[^>]*/', $codeParts[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = static::getTagAttributes($reg[0], false);
            $hrefData = [];
            $hrefData['ref'] = ($attributes['href'] ?? '') ?: ($attributes['action'] ?? '');
            $quotes = str_starts_with($hrefData['ref'], '"') ? '"' : '';
            $hrefData['ref'] = trim($hrefData['ref'], '"');
            if ($hrefData['ref']) {
                // Finds out if the value had quotes around it
                $hrefData['quotes'] = $quotes;
                // subst_str is the string to look for when substituting later on
                $hrefData['subst_str'] = $quotes . $hrefData['ref'] . $quotes;
                if (!str_starts_with(trim($hrefData['ref']), '#') && !str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = static::absRef($hrefData['ref'], $path);
                    $hrefData['tag'] = $tag;
                    $hrefData['no_jumpurl'] = intval(trim(($attributes['no_jumpurl'] ?? ''), '"')) ? 1 : 0;
                    $hyperLinks[] = $hrefData;
                }
            }
        }
        // todo remove, after matomo integration is finished
        // Extracts TYPO3 specific links made by the openPic() JS function
//        $codeParts = explode("onClick=\"openPic('", $content);
//        $pieces = count($codeParts);
//        for ($i = 1; $i < $pieces; $i++) {
//            $showpicArray = explode("'", $codeParts[$i]);
//            $hrefData['ref'] = $showpicArray[0];
//            if ($hrefData['ref']) {
//                $hrefData['quotes'] = "'";
//                // subst_str is the string to look for, when substituting lateron
//                $hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
//                if (!str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
//                    $linkList .= '|' . $hrefData['subst_str'] . '|';
//                    $hrefData['absRef'] = static::absRef($hrefData['ref'], $path);
//                    $hyperLinks[] = $hrefData;
//                }
//            }
//        }

        // todo remove, after matomo integration is finished
        // substitute dmailerping URL
        // get all media and search for use_jumpurl then add it to the hrefs array
//        $mediaLinks = static::extractMediaLinks($content, $path);
//
//        foreach ($mediaLinks as $mediaLink) {
//            if (isset($mediaLink['use_jumpurl']) && $mediaLink['use_jumpurl'] === 1) {
//                $hyperLinks[$mediaLink['ref']] = $mediaLink;
//            }
//        }

        return $hyperLinks;
    }

    /**
     * This function checks which content elements are supposed to be sent to the recipient.
     * tslib_content inserts dmail boundary markers in the content specifying which elements are intended for which categories,
     * this functions check if the recipient is subscribing to any of these categories and
     * filters out the elements that are inteded for categories not subscribed to.
     *
     * @param array $contentArray Array of content split by dmail boundary
     * @param string|null $userCategories The list of categories the user is subscribing to.
     *
     * @return array Content of the email, which the recipient subscribed
     */
    public static function getBoundaryParts(array $contentArray, string $userCategories = null): array
    {
        $contentParts = [];
        $mailHasContent = false;
        $boundaryMax = count($contentArray) - 1;
        foreach ($contentArray as $blockKey => $contentPart) {
            $key = substr($contentPart[0], 1);
            $isSubscribed = false;
            $contentPart['mediaList'] = $contentPart['mediaList'] ?? '';
            if (!$key || $userCategories === null) {
                $contentParts[] = $contentPart[1];
                if ($contentPart[1]) {
                    $mailHasContent = true;
                }
            } else {
                if ($key == 'END') {
                    $contentParts[] = $contentPart[1];
                    // There is content, and it is not just the header and footer content, or it is the only content because we have no direct mail boundaries.
                    if (($contentPart[1] && !($blockKey == 0 || $blockKey == $boundaryMax)) || count($contentArray) == 1) {
                        $mailHasContent = true;
                    }
                } else {
                    foreach (explode(',', $key) as $group) {
                        if (GeneralUtility::inList($userCategories, $group)) {
                            $isSubscribed = true;
                        }
                    }
                    if ($isSubscribed) {
                        $contentParts[] = $contentPart[1];
                        $mailHasContent = true;
                    }
                }
            }
        }
        return [
            $contentParts,
            $mailHasContent,
        ];
    }

    /*
     * CONTENT MODIFICATIONS
     */

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

    /**
     * @return string The created redirect url
     */
    public static function createRedirect(string $targetLink, int $lengthLimit, string $sourceHost): string
    {
        if (strlen($targetLink) <= $lengthLimit) {
            return $targetLink;
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

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
     * This substitutes the http:// urls in plain text with links
     *
     * @param string $content
     * @param string $jumpUrlPrefix
     * @param bool $jumpUrlUseId
     * @return array The changed content and plain link ids
     */
    public static function replaceUrlsInPlainText(string $content, string $jumpUrlPrefix, bool $jumpUrlUseId): array
    {
        $jumpUrlCounter = 1;
        $plainLinkIds = [];
        $contentWithReplacedUrls = preg_replace_callback(
            '/https?:\/\/\S+/',
            function ($urlMatches) use ($jumpUrlPrefix, $jumpUrlUseId, &$jumpUrlCounter, &$plainLinkIds) {
                $url = $urlMatches[0];
                if (str_contains($url, '&no_jumpurl=1')) {
                    // A link parameter "&no_jumpurl=1" allows to disable jumpurl for plain text links
                    $url = str_replace('&no_jumpurl=1', '', $url);
                } else {
                    if ($jumpUrlUseId) {
                        $plainLinkIds[$jumpUrlCounter] = $url;
                        $url = $jumpUrlPrefix . '-' . $jumpUrlCounter;
                        $jumpUrlCounter++;
                    } else {
                        $url = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($url));
                    }
                }
                return $url;
            },
            $content
        );

        return [
            $contentWithReplacedUrls,
            $plainLinkIds,
        ];
    }

    /**
     * replace hrefs in $content
     *
     * @param string $content
     * @param array $hrefs
     * @param string $jumpUrlPrefix
     * @param bool $enableJumpUrl
     * @param bool $jumpUrlUseMailto
     * @return string
     */
    public static function replaceHrefsInContent(string $content, array $hrefs, string $jumpUrlPrefix, bool $enableJumpUrl, bool $jumpUrlUseMailto): string
    {
        foreach ($hrefs as $urlId => $val) {
            if ($val['no_jumpurl'] ?? false) {
                // A tag attribute "no_jumpurl=1" allows to disable jumpurl for custom links
                $substVal = $val['absRef'];
            } else {
                if ($jumpUrlPrefix && ($val['tag'] !== 'form') && (!str_contains($val['ref'], 'mailto:'))) {
                    // Form elements cannot use jumpurl!
                    if ($enableJumpUrl) {
                        $substVal = $jumpUrlPrefix . $urlId;
                    } else {
                        $substVal = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                    }
                } else {
                    if (strstr($val['ref'], 'mailto:') && $jumpUrlUseMailto) {
                        if ($enableJumpUrl) {
                            $substVal = $jumpUrlPrefix . $urlId;
                        } else {
                            $substVal = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                        }
                    } else {
                        $substVal = $val['absRef'];
                    }
                }
            }
            $content = str_replace(
                $val['subst_str'],
                $val['quotes'] . $substVal . $val['quotes'],
                $content
            );
        }

        return $content;
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


    /*
     * MIXED HELPERS
     */

    /**
     * @param $mailUid
     * @param $tableName
     * @param $recipientUid
     * @return string
     */
    public static function buildMailIdentifierHeader($mailUid, $tableName, $recipientUid): string
    {
        $midRidId = 'MID' . $mailUid . '-' . $tableName . '-' . $recipientUid;
        return $midRidId . '-' . md5($midRidId);
    }

    /**
     * @param $content
     * @param $header
     * @return array|bool
     */
    public static function decodeMailIdentifierHeader($content, $header): array|bool
    {
        if (str_contains($content, $header)) {
            $p = explode($header, $content, 2);
            $l = explode(LF, $p[1], 2);
            [$mailUid, $hash] = GeneralUtility::trimExplode('-', $l[0]);
            if (md5($mailUid) === $hash) {
                // remove "MID" prefix and separate mailUid, tableName and recipientUid
                $moreParts = explode('-', substr($mailUid, 3));
                return [
                    'mail' => $moreParts[0],
                    'recipient_table' => $moreParts[1],
                    'recipient_uid' => $moreParts[2],
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
        $username = $params['http_username'] ?? '';
        $password = $params['http_password'] ?? '';
        $matches = [];
        if ($username && $password && preg_match('/^https?:\/\//', $url, $matches)) {
            $url = $matches[0] . $username . ':' . $password . '@' . substr($url, strlen($matches[0]));
        }
        if (($params['simulate_usergroup'] ?? false) && MathUtility::canBeInterpretedAsInteger($params['simulate_usergroup'])) {
            $glue = str_contains($url, '?') ? '&' : '?';
            $url .= $glue . 'dmail_fe_group=' . (int)$params['simulate_usergroup'] . '&access_token=' . RegistryUtility::createAndGetAccessToken();
        }
        return $url;
    }

    /**
     * Returns the absolute address of a link. This is based on
     * $this->theParts["html"]["path"] being the root-address
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
     * This function analyzes an HTML tag
     * If an attribute is empty (like OPTION) the value of that key is just empty.
     * Check it with is_set();
     *
     * @param string $tag Tag is either like this "<TAG OPTION ATTRIB=VALUE>" or
     *                 this " OPTION ATTRIB=VALUE>" which means you can omit the tag-name
     * @param boolean $removeQuotes When TRUE (default) quotes around a value will get removed
     *
     * @return array array with attributes as keys in lower-case
     */
    public static function getTagAttributes(string $tag, bool $removeQuotes = true): array
    {
        $attributes = [];
        $tag = ltrim(preg_replace('/^<[^ ]*/', '', trim($tag)));
        $tagLen = strlen($tag);
        $safetyCounter = 100;
        // Find attribute
        while ($tag) {
            $value = '';
            $reg = preg_split('/[[:space:]=>]/', $tag, 2);
            $attrib = $reg[0];

            $tag = ltrim(substr($tag, strlen($attrib), $tagLen));
            if (str_starts_with($tag, '=')) {
                $tag = ltrim(substr($tag, 1, $tagLen));
                if (str_starts_with($tag, '"') && $removeQuotes) {
                    // Quotes around the value
                    $reg = explode('"', substr($tag, 1, $tagLen), 2);
                    $tag = ltrim($reg[1]);
                    $value = $reg[0];
                } else {
                    // No quotes around value
                    preg_match('/^([^[:space:]>]*)(.*)/', $tag, $reg);
                    $value = trim($reg[1]);
                    $tag = ltrim($reg[2]);
                    if (str_starts_with($tag, '>')) {
                        $tag = '';
                    }
                }
            }
            $attributes[strtolower($attrib)] = $value;
            $safetyCounter--;
            if ($safetyCounter < 0) {
                break;
            }
        }
        return $attributes;
    }

    /**
     * Creates a regular expression out of a list of tags
     *
     * @param array|string $tags Array the list of tags
     *        (either as array or string if it is one tag)
     *
     * @return string the regular expression
     */
    public static function tagRegex(array|string $tags): string
    {
        $tags = !is_array($tags) ? [$tags] : $tags;
        $regexp = '/';
        $c = count($tags);
        foreach ($tags as $tag) {
            $c--;
            $regexp .= '<' . $tag . '[[:space:]]' . (($c) ? '|' : '');
        }
        return $regexp . '/i';
    }

    /**
     * Gets the unixtime as milliseconds.
     *
     * @return int The unixtime as milliseconds
     */
    public static function getMilliseconds(): int
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * @param Mail $mail
     * @return int
     */
    public static function getNumberOfRecipients(Mail $mail): int
    {
        $numberOfRecipients = 0;
        if ($mail->getQueryInfo() ?? false) {
            $queryInfo = unserialize($mail->getQueryInfo());
            if (isset($queryInfo['id_lists'])) {
                $numberOfRecipients = array_sum(array_map('count', $queryInfo['id_lists']));
            }
        }

        return $numberOfRecipients;
    }

    /**
     * @param int $sent
     * @param int $numberOfRecipients
     * @return array
     */
    public static function calculatePercentOfSend(int $sent, int $numberOfRecipients): array
    {
        if ($numberOfRecipients) {
            $percentOfSent = 100 / $numberOfRecipients * $sent;
            if ($percentOfSent > 100) {
                $percentOfSent = 100;
            }
            if ($percentOfSent < 0) {
                $percentOfSent = 0;
            }
        } else {
            $percentOfSent = $sent ? 100 : 0;
            $numberOfRecipients = $sent;
        }
        return [$percentOfSent, $numberOfRecipients];
    }
}
