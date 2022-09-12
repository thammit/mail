<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use FoT3\Rdct\Redirects;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailRepository;
use MEDIAESSENZ\Mail\Service\MailerService;
use PDO;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class MailerUtility
{
    /**
     * Get the ID of page in a tree
     *
     * @param int $id Page ID
     * @param string $perms_clause Select query clause
     * @return array the page ID, recursively
     */
    public static function getRecursiveSelect(int $id, string $perms_clause): array
    {
        // Finding tree and offer setting of values recursively.
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init('AND ' . $perms_clause);
        $tree->makeHTML = 0;
        $tree->setRecs = 0;
        $getLevels = 10000;
        $tree->getTree($id, $getLevels);

        return $tree->ids;
    }

    /**
     * Remove double record in an array
     *
     * @param array $plainlist Email of the recipient
     *
     * @return array Cleaned array
     */
    public static function cleanPlainList(array $plainlist): array
    {
        /**
         * $plainlist is a multidimensional array.
         * this method only remove if a value has the same array
         * $plainlist = [
         *        0 => [
         *            name => '',
         *            email => '',
         *        ],
         *        1 => [
         *            name => '',
         *            email => '',
         *        ],
         * ];
         */
        return array_map('unserialize', array_unique(array_map('serialize', $plainlist)));
    }

    /**
     * Rearrange emails array into a 2-dimensional array
     *
     * @param array $plainMails Recipient emails
     *
     * @return array a 2-dimensional array consisting email and name
     */
    public static function rearrangePlainMails(array $plainMails): array
    {
        $out = [];
        if (is_array($plainMails)) {
            $c = 0;
            foreach ($plainMails as $v) {
                $out[$c]['email'] = trim($v);
                $out[$c]['name'] = '';
                $c++;
            }
        }
        return $out;
    }

    /**
     * Get locallang label
     *
     * @param string $name Locallang label index
     *
     * @return string The label
     */
    public static function fName(string $name): string
    {
        return stripslashes(self::getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail', $name)));
    }

    /**
     * @return LanguageService
     */
    public static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Fetch content of a page (only internal and external page)
     *
     * @param array $row Directmail DB record
     * @param array $params Any default parameters (usually the ones from pageTSconfig)
     * @param bool $returnArray Return error or warning message as array instead of string
     *
     * @return array|string Error or warning message during fetching the content
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function fetchUrlContentsForMailRecord(array $row, array $params, bool $returnArray = false): array|string
    {
        $lang = self::getLanguageService();
        $theOutput = '';
        $errorMsg = [];
        $warningMsg = [];
        $urls = self::getFullUrlsForMailRecord($row);
        $plainTextUrl = $urls['plainTextUrl'];
        $htmlUrl = $urls['htmlUrl'];
        $urlBase = $urls['baseUrl'];
        $glue = (str_contains($urlBase, '?')) ? '&' : '?';

        // Compile the mail
        /* @var $mailerService MailerService */
        $mailerService = GeneralUtility::makeInstance(MailerService::class);
        if ($params['enable_jump_url']) {
            $mailerService->setJumpUrlPrefix($urlBase . $glue .
                'mid=###SYS_MAIL_ID###' .
                (intval($params['jumpurl_tracking_privacy']) ? '' : '&rid=###SYS_TABLE_NAME###_###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl=');
            $mailerService->setJumpUrlUseId(true);
        }
        if ($params['enable_mailto_jump_url']) {
            $mailerService->setJumpUrlUseMailto(true);
        }

        $mailerService->start();
        $mailerService->setCharset($row['charset']);
        $mailerService->setIncludeMedia((bool)$row['includeMedia']);

        if ($plainTextUrl) {
            $mailContent = GeneralUtility::getURL(self::addUserPass($plainTextUrl, $params));
            $mailerService->addPlainContent($mailContent);
            if (!$mailContent || !$mailerService->getPlainContent()) {
                $errorMsg[] = $lang->getLL('dmail_no_plain_content');
            } else if (!str_contains($mailerService->getPlainContent(), '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $lang->getLL('dmail_no_plain_boundaries');
            }
        }

        // fetch the HTML url
        if ($htmlUrl) {
            // Username and password is added in htmlmail object
            $success = $mailerService->addHTML(self::addUserPass($htmlUrl, $params));
            // If type = 1, we have an external page.
            if ($row['type'] == 1) {
                // Try to auto-detect the charset of the message
                $matches = [];
                $res = preg_match('/<meta\s+http-equiv="Content-Type"\s+content="text\/html;\s+charset=([^"]+)"/m', ($mailerService->getMailPart('html_content') ?? ''), $matches);
                if ($res == 1) {
                    $mailerService->setCharset($matches[1]);
                } else if (isset($params['direct_mail_charset'])) {
                    $mailerService->setCharset($params['direct_mail_charset']);
                } else {
                    $mailerService->setCharset('iso-8859-1');
                }
            }
            if (self::extractFramesInfo($mailerService->getHtmlContent(), $mailerService->getHtmlPath())) {
                $errorMsg[] = $lang->getLL('dmail_frames_not allowed');
            } else if (!$success || !$mailerService->getHtmlContent()) {
                $errorMsg[] = $lang->getLL('dmail_no_html_content');
            } else if (!str_contains($mailerService->getHtmlContent(), '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $lang->getLL('dmail_no_html_boundaries');
            }
        }

        if (!count($errorMsg)) {
            // Update the record:
            $mailerService->setMailPart('messageid', $mailerService->getMessageId());
            $mailContent = base64_encode(serialize($mailerService->getMailParts()));

            $updateData = [
                'issent' => 0,
                'charset' => $mailerService->getCharset(),
                'mailContent' => $mailContent,
                'renderedSize' => strlen($mailContent),
                'long_link_rdct_url' => $urlBase,
            ];

            GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord((int)$row['uid'], $updateData);

            if (count($warningMsg)) {
                foreach ($warningMsg as $warning) {
                    $theOutput .= GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
                        ->resolve()
                        ->render([
                            GeneralUtility::makeInstance(
                                FlashMessage::class,
                                $warning,
                                $lang->getLL('dmail_warning'),
                                AbstractMessage::WARNING
                            ),
                        ]);
                }
            }
        } else {
            foreach ($errorMsg as $error) {
                $theOutput .= GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
                    ->resolve()
                    ->render([
                        GeneralUtility::makeInstance(
                            FlashMessage::class,
                            $error,
                            $lang->getLL('dmail_error'),
                            AbstractMessage::ERROR
                        ),
                    ]);
            }
        }
        if ($returnArray) {
            return [
                'errors' => $errorMsg,
                'warnings' => $warningMsg,
            ];
        } else {
            return $theOutput;
        }
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
    protected static function addUserPass(string $url, array $params): string
    {
        $user = $params['http_username'] ?? '';
        $pass = $params['http_password'] ?? '';
        $matches = [];
        if ($user && $pass && preg_match('/^https?:\/\//', $url, $matches)) {
            $url = $matches[0] . $user . ':' . $pass . '@' . substr($url, strlen($matches[0]));
        }
        if (($params['simulate_usergroup'] ?? false) && MathUtility::canBeInterpretedAsInteger($params['simulate_usergroup'])) {
            $glue = (str_contains($url, '?')) ? '&' : '?';
            $url = $url . $glue . 'dmail_fe_group=' . (int)$params['simulate_usergroup'] . '&access_token=' . GeneralUtility::makeInstance(RegistryUtility::class)->createAndGetAccessToken();
        }
        return $url;
    }

    /**
     * Set up URL variables for this $row.
     *
     * @param array $row Directmail DB record
     *
     * @return array $result Url_plain and url_html in an array
     */
    public static function getFullUrlsForMailRecord(array $row): array
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        // Finding the domain to use
        $result = [
            'baseUrl' => $cObj->typolink_URL([
                'parameter' => 't3://page?uid=' . (int)$row['page'],
                'forceAbsoluteUrl' => true,
                'linkAccessRestrictedPages' => true,
            ]),
            'htmlUrl' => '',
            'plainTextUrl' => '',
        ];

        // Finding the url to fetch content from
        switch ((string)$row['type']) {
            case 1:
                $result['htmlUrl'] = $row['HTMLParams'];
                $result['plainTextUrl'] = $row['plainParams'];
                break;
            default:
                $params = str_starts_with($row['HTMLParams'], '&') ? substr($row['HTMLParams'], 1) : $row['HTMLParams'];
                $result['htmlUrl'] = $cObj->typolink_URL([
                    'parameter' => 't3://page?uid=' . (int)$row['page'] . '&' . $params,
                    'forceAbsoluteUrl' => true,
                    'linkAccessRestrictedPages' => true,
                ]);
                $params = str_starts_with($row['plainParams'], '&') ? substr($row['plainParams'], 1) : $row['plainParams'];
                $result['plainTextUrl'] = $cObj->typolink_URL([
                    'parameter' => 't3://page?uid=' . (int)$row['page'] . '&' . $params,
                    'forceAbsoluteUrl' => true,
                    'linkAccessRestrictedPages' => true,
                ]);
        }

        // plain
        if ($result['plainTextUrl']) {
            if (!($row['sendOptions'] & 1)) {
                $result['plainTextUrl'] = '';
            } else {
                $urlParts = @parse_url($result['plainTextUrl']);
                if (!$urlParts['scheme']) {
                    $result['plainTextUrl'] = 'http://' . $result['plainTextUrl'];
                }
            }
        }

        // html
        if ($result['htmlUrl']) {
            if (!($row['sendOptions'] & 2)) {
                $result['htmlUrl'] = '';
            } else {
                $urlParts = @parse_url($result['htmlUrl']);
                if (!$urlParts['scheme']) {
                    $result['htmlUrl'] = 'http://' . $result['htmlUrl'];
                }
            }
        }

        return $result;
    }

    /**
     * Get the configured charset.
     *
     * This method used to initialize the TSFE object to get the charset on a per-page basis. Now it just evaluates the
     * configured charset of the instance
     *
     * @return string
     * @throws InvalidConfigurationTypeException
     */
    public static function getCharacterSet(): string
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $characterSet = 'utf-8';

        if ($settings['config.']['metaCharset']) {
            $characterSet = $settings['config.']['metaCharset'];
        } else if ($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']) {
            $characterSet = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
        }

        return mb_strtolower($characterSet);
    }

    /**
     * Wrapper for the old t3lib_div::intInRange.
     * Forces the integer $theInt into the boundaries of $min and $max.
     * If the $theInt is 'FALSE' then the $zeroValue is applied.
     */
    public static function intInRangeWrapper(int $theInt, int $min, int $max = 2000000000, int $zeroValue = 0): int
    {
        return MathUtility::forceIntegerInRange($theInt, $min, $max, $zeroValue);
    }

    /**
     * Takes a clear-text message body for a plain text email, finds all 'http://' links and if they are longer than 76 chars they are converted to a shorter URL with a hash parameter.
     * The real parameter is stored in the database and the hash-parameter/URL will be redirected to the real parameter when the link is clicked.
     * This function is about preserving long links in messages.
     *
     * @param string $message Message content
     * @param string $urlmode URL mode; "76" or "all
     * @param string $index_script_url URL of index script (see makeRedirectUrl())
     * @return string Processed message content
     * @see makeRedirectUrl()
     * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8. Use mailer API instead
     */
    public static function substUrlsInPlainText(string $message, string $urlmode = '76', string $index_script_url = ''): string
    {
        $lengthLimit = match ($urlmode) {
            '' => false,
            'all' => 0,
            default => (int)$urlmode,
        };
        if ($lengthLimit === false) {
            // No processing
            $messageSubstituted = $message;
        } else {
            $messageSubstituted = preg_replace_callback(
                '/(http|https):\\/\\/.+(?=[].?]*([! \'"()<>]+|$))/iU',
                function (array $matches) use ($lengthLimit, $index_script_url) {
                    $redirects = GeneralUtility::makeInstance(Redirects::class);
                    return $redirects->makeRedirectUrl($matches[0], $lengthLimit, $index_script_url);
                },
                $message
            );
        }
        return $messageSubstituted;
    }

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
     * generate edit link for records
     *
     * @param $params
     * @return string
     * @throws RouteNotFoundException
     */
    public static function getEditOnClickLink($params): string
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return 'window.location.href=' . GeneralUtility::quoteJSvalue((string)$uriBuilder->buildUriFromRoute('record_edit', $params)) . '; return false;';
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public static function getRecordOverlay(string $table, array $row, int $sys_language_content, string $OLmode = ''): array
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
                            $olrow = $queryBuilder->select('*')
                                ->from($table)
                                ->add('where', 'pid=' . intval($row['pid']) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . $sys_language_content .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($row['uid']))
                                ->setMaxResults(1)/* LIMIT 1*/
                                ->execute()
                                ->fetchAssociative();

                            // Merge record content by traversing all fields:
                            if (is_array($olrow)) {
                                foreach ($row as $fN => $fV) {
                                    if ($fN != 'uid' && $fN != 'pid' && isset($olrow[$fN])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'mergeIfNotBlank' || strcmp(trim($olrow[$fN]), ''))) {
                                            $row[$fN] = $olrow[$fN];
                                        }
                                    }
                                }
                            } else if ($OLmode === 'hideNonTranslated' && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0) {
                                // Unset, if non-translated records should be hidden.
                                // ONLY done if the source record really is default language and not [All] in which case it is allowed.
                                unset($row);
                            }

                            // Otherwise, check if sys_language_content is different from the value of the record
                            // that means a japanese site might try to display french content.
                        } else if ($sys_language_content != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                            unset($row);
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
     * Converting array key.
     * fe_user and tt_address are using different fieldname for the same information
     *
     * @param array $recipRow Recipient's data array
     *
     * @return array Fixed recipient's data array
     */
    public static function convertFields(array $recipRow): array
    {
        // Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
        if ($recipRow['telephone'] ?? false) {
            $recipRow['phone'] = $recipRow['telephone'];
        }

        // Firstname must be more than 1 character
        $recipRow['firstname'] = trim(strtok(trim($recipRow['name']), ' '));
        if (strlen($recipRow['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipRow['firstname'])) {
            $recipRow['firstname'] = $recipRow['name'];
        }
        if (!trim($recipRow['firstname'])) {
            $recipRow['firstname'] = $recipRow['email'];
        }
        return $recipRow;
    }

    /**
     * Creates an address object ready to be used with the symonfy mailer
     *
     * @param string $email
     * @param string|NULL $name
     * @return Address
     */
    public static function createRecipient(string $email, string $name = null): Address
    {
        if (!empty($name)) {
            $recipient = new Address($email, $name);
        } else {
            $recipient = new Address($email);
        }

        return $recipient;
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
            // if ref is an url
            // do nothing
        } else if (preg_match('/^\//', $ref)) {
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
    public static function get_tag_attributes(string $tag, bool $removeQuotes = true): array
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
    public static function tag_regex(array|string $tags): string
    {
        $tags = (!is_array($tags) ? [$tags] : $tags);
        $regexp = '/';
        $c = count($tags);
        foreach ($tags as $tag) {
            $c--;
            $regexp .= '<' . $tag . '[[:space:]]' . (($c) ? '|' : '');
        }
        return $regexp . '/i';
    }

    /**
     * Extracts all media-links from $this->theParts["html"]["content"]
     *
     * @return    array    two-dimensional array with information about each frame
     */
    public static function extractFramesInfo(string $content, string $path): array
    {
        $htmlCode = $content;
        $info = [];
        if (strpos(' ' . $htmlCode, '<frame ')) {
            $attribRegex = MailerUtility::tag_regex('frame');
            // Splits the document by the beginning of the above tags
            $codepieces = preg_split($attribRegex, $htmlCode, 1000000);
            $pieces = count($codepieces);
            for ($i = 1; $i < $pieces; $i++) {
                preg_match('/[^>]*/', $codepieces[$i], $reg);
                // Fetches the attributes for the tag
                $attributes = MailerUtility::get_tag_attributes($reg[0]);
                $frame = [];
                $frame['src'] = $attributes['src'];
                $frame['name'] = $attributes['name'];
                $frame['absRef'] = MailerUtility::absRef($frame['src'], $path);
                $info[] = $frame;
            }
        }
        return $info;
    }

    /**
     * This substitutes the http:// urls in plain text with links
     *
     * @param MailerService $mailerService
     * @return void The changed content
     */
    public static function substHTTPurlsInPlainText(MailerService $mailerService): void
    {
        $jumpUrlPrefix = $mailerService->getJumpUrlPrefix();
        if (empty($jumpUrlPrefix)) {
            return;
        }

        $jumpUrlUseId = $mailerService->getJumpUrlUseId();
        $content = $mailerService->getPlainContent();

        $jumpUrlCounter = 1;
        $plainLinkIds = [];
        $contentWithReplacedUrls = preg_replace_callback(
            '/https?:\/\/\S+/',
            function ($urlMatches) use ($jumpUrlPrefix, $jumpUrlUseId, &$jumpUrlCounter, &$plainLinkIds) {
                $url = $urlMatches[0];
                if (str_contains($url, '&no_jumpurl=1')) {
                    // A link parameter "&no_jumpurl=1" allows to disable jumpurl for plain text links
                    $url = str_replace('&no_jumpurl=1', '', $url);
                } else if ($jumpUrlUseId) {
                    $plainLinkIds[$jumpUrlCounter] = $url;
                    $url = $jumpUrlPrefix . '-' . $jumpUrlCounter;
                    $jumpUrlCounter++;
                } else {
                    $url = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($url));
                }
                return $url;
            },
            $content
        );

        $mailerService->setPlainLinkIds($plainLinkIds);
        $mailerService->setPlainContent($contentWithReplacedUrls);
    }

    /**
     * substitutes hrefs in $this->theParts["html"]["content"]
     *
     * @param MailerService $mailerService
     * @return void
     */
    public static function substHREFsInHTML(MailerService $mailerService): void
    {
        if (empty($mailerService->getHtmlHrefs())) {
            return;
        }
        $hrefs = $mailerService->getHtmlHrefs();
        $jumpUrlPrefix = $mailerService->getJumpUrlPrefix();
        $jumpUrlUseId = $mailerService->getJumpUrlUseId();
        $jumpUrlUseMailto = $mailerService->getJumpUrlUseMailto();

        foreach ($hrefs as $urlId => $val) {
            if (isset($val['no_jumpurl'])) {
                // A tag attribute "no_jumpurl=1" allows to disable jumpurl for custom links
                $substVal = $val['absRef'];
            } else if ($jumpUrlPrefix && ($val['tag'] != 'form') && (!str_contains($val['ref'], 'mailto:'))) {
                // Form elements cannot use jumpurl!
                if ($jumpUrlUseId) {
                    $substVal = $jumpUrlPrefix . $urlId;
                } else {
                    $substVal = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                }
            } else if (strstr($val['ref'], 'mailto:') && $jumpUrlUseMailto) {
                if ($jumpUrlUseId) {
                    $substVal = $jumpUrlPrefix . $urlId;
                } else {
                    $substVal = $jumpUrlPrefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                }
            } else {
                $substVal = $val['absRef'];
            }
            $mailerService->setHtmlContent(str_replace(
                $val['subst_str'],
                $val['quotes'] . $substVal . $val['quotes'],
                $mailerService->getHtmlContent()
            ));
        }
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
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param string $rid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parseTime Parse time of the email
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return int
     * @throws DBALException
     */
    public static function addToMailLog(int $mid, string $rid, int $size, int $parseTime, int $html, string $email): int
    {
        [$rtbl, $rid] = explode('_', $rid);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $queryBuilder
            ->insert('sys_dmail_maillog')
            ->values([
                'mid' => $mid,
                'rtbl' => $rtbl,
                'rid' => $rid,
                'email' => $email,
                'tstamp' => time(),
                'url' => '',
                'size' => $size,
                'parsetime' => $parseTime,
                'html_sent' => $html,
            ])
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId('sys_dmail_maillog');
    }

    /**
     * Get comma separated list of recipient ids, which has been sent
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail record
     * @param string $table Recipient table
     *
     * @return string        list of sent recipients
     * @throws DBALException
     * @throws Exception
     */
    public static function getSentMails(int $mailUid, string $table): string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $statement = $queryBuilder
            ->select('rid')
            ->from('sys_dmail_maillog')
            ->where($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($table)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        $list = '';

        while (($row = $statement->fetchAssociative())) {
            $list .= $row['rid'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail record
     * @param int $recipientUid Recipient UID
     * @param string $table Recipient table
     *
     * @return bool Number of found records
     * @throws DBALException
     */
    public static function isMailSendToRecipient(int $mailUid, int $recipientUid, string $table): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');

        $statement = $queryBuilder
            ->select('uid')
            ->from('sys_dmail_maillog')
            ->where($queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($recipientUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($table)))
            ->andWhere($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        return (bool)$statement->rowCount();
    }

    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table Tablename of the recipient
     * @param int $uid Uid of the recipient
     *
     * @return string        list of categories
     * @throws DBALException
     * @throws Exception
     */
    public static function getListOfRecipientCategories(string $table, int $uid): string
    {
        if ($table === 'PLAINLIST') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select($relationTable . '.uid_foreign')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_local = ' . $table . '.uid')
            ->where($queryBuilder->expr()->eq($relationTable . '.uid_local', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->execute();

        $list = '';
        while ($row = $statement->fetchAssociative()) {
            $list .= $row['uid_foreign'] . ',';
        }

        return rtrim($list, ',');
    }

    /**
     * @param string $path
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getExtensionConfiguration(string $path = ''): string
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('mail', $path);
    }
}
