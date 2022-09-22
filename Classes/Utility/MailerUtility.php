<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use FoT3\Rdct\Redirects;
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
        } catch (\Exception $exception) {
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

        $attribRegex = static::tagRegex(['img', 'table', 'td', 'tr', 'body', 'iframe', 'script', 'input', 'embed']);
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
            $imageData['ref'] = ($attributes['src'] ?? $attributes['background'] ?? '');
            if ($imageData['ref']) {
                // find out if the value had quotes around it
                $imageData['quotes'] = (substr($codeParts[$i], strpos($codeParts[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
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
                if ($imageData['ref'] && !str_contains($imageList, '|' . $imageData['subst_str'] . '|')) {
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
     * Extracts all hyper-links from given content
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
            $quotes = (str_starts_with($hrefData['ref'], '"')) ? '"' : '';
            $hrefData['ref'] = trim($hrefData['ref'], '"');
            if ($hrefData['ref']) {
                // Finds out if the value had quotes around it
                $hrefData['quotes'] = $quotes;
                // subst_str is the string to look for when substituting later on
                $hrefData['subst_str'] = $quotes . $hrefData['ref'] . $quotes;
                if ($hrefData['ref'] && !str_starts_with(trim($hrefData['ref']), '#') && !str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = static::absRef($hrefData['ref'], $path);
                    $hrefData['tag'] = $tag;
                    $hrefData['no_jumpurl'] = intval(trim(($attributes['no_jumpurl'] ?? ''), '"')) ? 1 : 0;
                    $hyperLinks[] = $hrefData;
                }
            }
        }
        // Extracts TYPO3 specific links made by the openPic() JS function
        $codeParts = explode("onClick=\"openPic('", $content);
        $pieces = count($codeParts);
        for ($i = 1; $i < $pieces; $i++) {
            $showpicArray = explode("'", $codeParts[$i]);
            $hrefData['ref'] = $showpicArray[0];
            if ($hrefData['ref']) {
                $hrefData['quotes'] = "'";
                // subst_str is the string to look for, when substituting lateron
                $hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
                if (!str_contains($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = static::absRef($hrefData['ref'], $path);
                    $hyperLinks[] = $hrefData;
                }
            }
        }

        // substitute dmailerping URL
        // get all media and search for use_jumpurl then add it to the hrefs array
        $mediaLinks = static::extractMediaLinks($content, $path);

        foreach ($mediaLinks as $mediaLink) {
            if (isset($mediaLink['use_jumpurl']) && $mediaLink['use_jumpurl'] === 1) {
                $hyperLinks[$mediaLink['ref']] = $mediaLink;
            }
        }

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
     * @param string $index_script_url URL of index script (see makeRedirectUrl())
     * @return string Processed message content
     */
    public static function shortUrlsInPlainText(string $message, int $lengthLimit = 76, string $index_script_url = ''): string
    {
        return preg_replace_callback(
            '/(http|https):\\/\\/.+(?=[].?]*([! \'"()<>]+|$))/iU',
            function (array $matches) use ($lengthLimit, $index_script_url) {
                $redirects = GeneralUtility::makeInstance(Redirects::class);
                return $redirects->makeRedirectUrl($matches[0], $lengthLimit, $index_script_url);
            },
            $message
        );
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
        if (empty($jumpUrlPrefix)) {
            $content;
        }

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
     * @param bool $jumpUrlUseId
     * @param bool $jumpUrlUseMailto
     * @return string
     */
    public static function replaceHrefsInContent(string $content, array $hrefs, string $jumpUrlPrefix, bool $jumpUrlUseId, bool $jumpUrlUseMailto): string
    {
        foreach ($hrefs as $urlId => $val) {
            if (isset($val['no_jumpurl'])) {
                // A tag attribute "no_jumpurl=1" allows to disable jumpurl for custom links
                $substVal = $val['absRef'];
            } else {
                if ($jumpUrlPrefix && ($val['tag'] != 'form') && (!str_contains($val['ref'], 'mailto:'))) {
                    // Form elements cannot use jumpurl!
                    if ($jumpUrlUseId) {
                        $substVal = $jumpUrlPrefix . $urlId;
                    } else {
                        $substVal = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                    }
                } else {
                    if (strstr($val['ref'], 'mailto:') && $jumpUrlUseMailto) {
                        if ($jumpUrlUseId) {
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
     * Fetches the attachment files referenced in the sys_dmail record.
     *
     * @param int $dmailUid The uid of the sys_dmail record to fetch the records for
     * @return array An array of FileReferences
     */
    public static function getAttachments(int $dmailUid): array
    {
        return GeneralUtility::makeInstance(FileRepository::class)->findByRelation('sys_dmail', 'attachment', $dmailUid);
    }

    /**
     * Get the fully-qualified domain name of the host
     * Copy from TYPO3 v9.5, will be removed in TYPO3 v10.0
     *
     * @param bool $requestHost Use request host (when not in CLI mode).
     * @return string The fully-qualified host name.
     */
    public static function getHostname(bool $requestHost = true): string
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
        return $host;
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
            $ref = $addr['scheme'] . '://' . $addr['host'] . (($addr['port'] ?? false) ? ':' . $addr['port'] : '') . $ref;
        } else {
            // If the reference is relative, the path is added,
            // in order for us to fetch the content
            if (str_ends_with($path, '/')) {
                // if the last char is a /, then prepend the ref
                $ref = $path . $ref;
            } else {
                // if the last char not a /, then assume it's an absolute
                $addr = parse_url($path);
                $ref = $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . '/' . $ref;
            }
        }

        return $ref;
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


    /*
     * CURRENTLY NOT USED STUFF
     */

    /**
     * @param string $table
     * @param array $row
     * @param int $sys_language_content
     * @param string $overlayMode
     * @return array
     * @throws DBALException
     * @throws Exception
     * todo Not used!
     */
    public static function getRecordOverlay(string $table, array $row, int $sys_language_content, string $overlayMode = ''): array
    {
        if ($row['uid'] > 0 && $row['pid'] > 0) {
            if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
                if (!isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable'])) {
                    // Will try to overlay a record only
                    // if the sys_language_content value is larger that zero.
                    if ($sys_language_content > 0) {
                        // Must be default language or [All], otherwise no overlaying:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] <= 0) {
                            // Select overlay record:
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $overlayRow = $queryBuilder->select('*')
                                ->from($table)
                                ->add('where', 'pid=' . intval($row['pid']) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . $sys_language_content .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($row['uid']))
                                ->setMaxResults(1)/* LIMIT 1*/
                                ->execute()
                                ->fetchAssociative();

                            // Merge record content by traversing all fields:
                            if (is_array($overlayRow)) {
                                foreach ($row as $fieldName => $fieldValue) {
                                    if ($fieldName != 'uid' && $fieldName != 'pid' && isset($overlayRow[$fieldName])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fieldName] != 'exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fieldName] != 'mergeIfNotBlank' || strcmp(trim($overlayRow[$fieldName]),
                                                    ''))) {
                                            $row[$fieldName] = $overlayRow[$fieldName];
                                        }
                                    }
                                }
                            } else {
                                if ($overlayMode === 'hideNonTranslated' && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0) {
                                    // Unset, if non-translated records should be hidden.
                                    // ONLY done if the source record really is default language and not [All] in which case it is allowed.
                                    unset($row);
                                }
                            }

                            // Otherwise, check if sys_language_content is different from the value of the record
                            // that means a japanese site might try to display french content.
                        } else {
                            if ($sys_language_content != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                                unset($row);
                            }
                        }
                    } else {
                        // When default language is displayed,
                        // we never want to return a record carrying another language!:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
                            unset($row);
                        }
                    }
                }
            }
        }

        return $row;
    }
}
