<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use FoT3\Rdct\Redirects;
use GuzzleHttp\Exception\RequestException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Database\QueryGenerator;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailGroupRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use PDO;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
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
    public static function removeDuplicates(array $plainlist): array
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
    public static function reArrangePlainMails(array $plainMails): array
    {
        $out = [];
        $c = 0;
        foreach ($plainMails as $v) {
            $out[$c]['email'] = trim($v);
            $out[$c]['name'] = '';
            $c++;
        }
        return $out;
    }

    /**
     * Get translated label of table column
     * default table: sys_dmail
     *
     * @param string $columnName
     * @param string $table
     * @return string The label
     */
    public static function getTranslatedLabelOfTcaField(string $columnName, string $table = 'sys_dmail'): string
    {
        return stripslashes(static::getLanguageService()->sL(BackendUtility::getItemLabel($table, $columnName)));
    }

    /**
     * @return LanguageService
     */
    public static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param string $index
     * @return string
     */
    public static function getLL(string $index): string
    {
        return static::getLanguageService()->getLL($index);
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    public static function isAdmin(): bool
    {
        return static::getBackendUser()->isAdmin();
    }

    public static function backendUserPermissions(): string
    {
        return static::getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
    }

    public static function getTSConfig(): array
    {
        return static::getBackendUser()->getTSConfig();
    }

    /**
     * @param $url
     * @return string|bool
     * @throws RequestException $exception
     */
    public static function fetchContentFromUrl($url): string|bool
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $response = $requestFactory->request($url);
        return $response->getBody()->getContents();
    }

    public static function contentContainsBoundaries($content): bool
    {
        return str_contains($content, '<!--DMAILER_SECTION_BOUNDARY');
    }

    public static function addMessageToFlashMessageQueue(string $message, string $title, int $severity, string $identifier = 'core.template.flashMessages'): void
    {
        static::getFlashMessageQueue($identifier)->addMessage(static::getFlashMessage($message, $title, $severity));
    }

    public static function addErrorToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        static::addMessageToFlashMessageQueue($message, $title, AbstractMessage::ERROR, $identifier);
    }

    public static function addWarningToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        static::addMessageToFlashMessageQueue($message, $title, AbstractMessage::WARNING, $identifier);
    }

    public static function addInfoToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        static::addMessageToFlashMessageQueue($message, $title, AbstractMessage::INFO, $identifier);
    }

    public static function addOkToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        static::addMessageToFlashMessageQueue($message, $title, AbstractMessage::OK, $identifier);
    }

    public static function getFlashMessageQueue(string $identifier = 'core.template.flashMessages'): FlashMessageQueue
    {
        return GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier($identifier);
    }

    public static function getFlashMessage(string $message, string $title, int $severity, bool $storeInSession = false): FlashMessage
    {
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            $storeInSession
        );
    }

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

    public static function getAbsoluteBaseUrlForMailPage(int $pageUid): string
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        // Finding the domain to use
        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid,
            'forceAbsoluteUrl' => true,
            'linkAccessRestrictedPages' => true,
        ]);
    }

    /**
     * @param int $pageUid
     * @param string $params
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getUrlForInternalPage(int $pageUid, string $params): string
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $params = str_starts_with($params, '&') ? substr($params, 1) : $params;

        return $contentObjectRenderer->typolink_URL([
            'parameter' => 't3://page?uid=' . $pageUid . '&' . $params,
            'forceAbsoluteUrl' => true,
            'forceAbsoluteUrl.' => ['scheme' => static::getDefaultScheme()],
            'linkAccessRestrictedPages' => true,
        ]);
    }

    /**
     * @param string $url
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getUrlForExternalPage(string $url): string
    {
        return @parse_url($url, PHP_URL_SCHEME) ? $url : static::getDefaultScheme() . '://' . $url;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public static function getDefaultScheme(): string
    {
        return static::getExtensionConfiguration('UseHttpToFetch') ? 'http' : 'https';
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function shouldFetchPlainText(array $config): bool
    {
        return ($config['sendOptions'] & 1) !== 0;
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function shouldFetchHtml(array $config): bool
    {
        return ($config['sendOptions'] & 2) !== 0;
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

        if (isset($settings['config.']['metaCharset'])) {
            $characterSet = $settings['config.']['metaCharset'];
        } else {
            if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])) {
                $characterSet = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
            }
        }

        return mb_strtolower($characterSet);
    }

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
     * @param string $table
     * @param array $row
     * @param int $sys_language_content
     * @param string $overlayMode
     * @return array
     * @throws DBALException
     * @throws Exception
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
     * Normalize address
     * fe_user and tt_address are using different field names for the same information
     *
     * @param array $recipientData Recipient's data array
     *
     * @return array Fixed recipient's data array
     */
    public static function normalizeAddress(array $recipientData): array
    {
        // Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
        if ($recipientData['telephone'] ?? false) {
            $recipientData['phone'] = $recipientData['telephone'];
        }

        // Firstname must be more than 1 character
        $token = strtok(trim($recipientData['name']), ' ');
        $recipientData['firstname'] = $token ? trim($token) : '';
        if (strlen($recipientData['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['name'];
        }
        if (!trim($recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['email'];
        }
        return $recipientData;
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
     * Standard authentication code (used in Direct Mail, checkJumpUrl and setfixed links computations)
     *
     * @param int|array $uid_or_record Uid (int) or record (array)
     * @param string $fields List of fields from the record if that is given.
     * @param int $codeLength Length of returned authentication code.
     * @return string MD5 hash of 8 chars.
     */
    public static function stdAuthCode(int|array $uid_or_record, string $fields = '', int $codeLength = 8): string
    {
        if (is_array($uid_or_record)) {
            $recCopy_temp = [];
            if ($fields) {
                $fieldArr = GeneralUtility::trimExplode(',', $fields, true);
                foreach ($fieldArr as $k => $v) {
                    $recCopy_temp[$k] = $uid_or_record[$v];
                }
            } else {
                $recCopy_temp = $uid_or_record;
            }
            $preKey = implode('|', $recCopy_temp);
        } else {
            $preKey = $uid_or_record;
        }
        $authCode = $preKey . '||' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        return substr(md5($authCode), 0, $codeLength);
    }

    /**
     * Fetches recipient IDs from a given group ID
     * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
     *
     * @param int $pageId Page ID
     * @param int $groupUid Recipient group ID
     * @param string $userTable
     * @param $backendUserPermissions
     * @return array List of recipient IDs
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function getSingleMailGroup(int $pageId, int $groupUid, string $userTable, $backendUserPermissions): array
    {
        $idLists = [];
        if ($groupUid) {
            $sysDmailGroupRepository = GeneralUtility::makeInstance(SysDmailGroupRepository::class);
            $mailGroup = $sysDmailGroupRepository->findByUid($groupUid);

            if (is_array($mailGroup)) {
                $tempRepository = GeneralUtility::makeInstance(TempRepository::class);
                switch ($mailGroup['type']) {
                    case Constants::RECIPIENT_GROUP_TYPE_PAGES:
                        // From pages
                        // use current page if not set in mail group
                        $thePages = $mailGroup['pages'] ?? $pageId;
                        // Explode the pages
                        $pages = GeneralUtility::intExplode(',', $thePages);
                        $pageIdArray = [];

                        foreach ($pages as $pageUid) {
                            if ($pageUid > 0) {
                                $pageinfo = BackendUtility::readPageAccess($pageUid, $backendUserPermissions);
                                if (is_array($pageinfo)) {
                                    $pageIdArray[] = $pageUid;
                                    if ($mailGroup['recursive']) {
                                        $pageIdArray = array_merge($pageIdArray, static::getRecursiveSelect($pageUid, $backendUserPermissions));
                                    }
                                }
                            }
                        }
                        // Remove any duplicates
                        $pageIdArray = array_unique($pageIdArray);
                        $pidList = implode(',', $pageIdArray);

                        // Make queries
                        if ($pidList) {
                            $whichTables = intval($mailGroup['whichtables']);
                            if ($whichTables & 1) {
                                // tt_address
                                $idLists['tt_address'] = $tempRepository->getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables & 2) {
                                // fe_users
                                $idLists['fe_users'] = $tempRepository->getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($userTable && ($whichTables & 4)) {
                                // user table
                                $idLists[$userTable] = $tempRepository->getIdList($userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables & 8) {
                                // fe_groups
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = $tempRepository->getIdList('fe_groups', $pidList, $groupUid, $mailGroup['select_categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users']));
                            }
                        }
                        break;
                    case Constants::RECIPIENT_GROUP_TYPE_CSV:
                        // List of mails
                        if ($mailGroup['csv'] == 1) {
                            $dmCsvUtility = GeneralUtility::makeInstance(CsvUtility::class);
                            $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroup['list']));
                        } else {
                            $recipients = static::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = static::removeDuplicates($recipients);
                        break;
                    case Constants::RECIPIENT_GROUP_TYPE_STATIC:
                        // Static MM list
                        $idLists['tt_address'] = $tempRepository->getStaticIdList('tt_address', $groupUid);
                        $idLists['fe_users'] = $tempRepository->getStaticIdList('fe_users', $groupUid);
                        $tempGroups = $tempRepository->getStaticIdList('fe_groups', $groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                        if ($userTable) {
                            $idLists[$userTable] = $tempRepository->getStaticIdList($userTable, $groupUid);
                        }
                        break;
                    case Constants::RECIPIENT_GROUP_TYPE_QUERY:
                        // Special query list
                        // Todo Remove that shit!
                        $queryTable = GeneralUtility::_GP('SET')['queryTable'];
                        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');
                        $mailGroup = $sysDmailGroupRepository->updateMailGroup($mailGroup, $userTable, $queryTable, $queryConfig);
                        $whichTables = intval($mailGroup['whichtables']);
                        $table = '';
                        if ($whichTables & 1) {
                            $table = 'tt_address';
                        } else if ($whichTables & 2) {
                            $table = 'fe_users';
                        } else if ($userTable && ($whichTables & 4)) {
                            $table = $userTable;
                        }
                        if ($table) {
                            $idLists[$table] = $tempRepository->getSpecialQueryIdList($table, $mailGroup);
                        }
                        break;
                    case Constants::RECIPIENT_GROUP_TYPE_OTHER:
                        $groups = array_unique($tempRepository->getMailGroups($mailGroup['mail_groups'], [$mailGroup['uid']], $backendUserPermissions));
                        foreach ($groups as $groupUid) {
                            $collect = static::getSingleMailGroup($pageId, $groupUid, $userTable, $backendUserPermissions);
                            if (is_array($collect)) {
                                $idLists = array_merge_recursive($idLists, $collect);
                            }
                        }
                        break;
                    default:
                }
            }
        }
        return $idLists;
    }


    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     * @throws DBALException
     */
    public static function compileMailGroup(int $pageId, array $groups, string $userTable, $backendUserPermissions): array
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (!count($groups)) {
            return [];
        }

        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $groupId) {
            // Testing to see if group ID is a valid integer, if not - skip to next group ID
            $groupId = MathUtility::convertToPositiveInteger($groupId);
            if (!$groupId) {
                continue;
            }

            $recipientList = static::getSingleMailGroup($pageId, $groupId, $userTable, $backendUserPermissions);
            if (!is_array($recipientList)) {
                continue;
            }

            $idLists = array_merge_recursive($idLists, $recipientList);
        }

        // Make unique entries
        if (is_array($idLists['tt_address'] ?? false)) {
            $idLists['tt_address'] = array_unique($idLists['tt_address']);
        }

        if (is_array($idLists['fe_users'] ?? false)) {
            $idLists['fe_users'] = array_unique($idLists['fe_users']);
        }

        if (is_array($idLists[$userTable] ?? false) && $userTable) {
            $idLists[$userTable] = array_unique($idLists[$userTable]);
        }

        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $idLists['PLAINLIST'] = static::removeDuplicates($idLists['PLAINLIST']);
        }

        return $idLists;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     * @throws DBALException
     */
    public static function finalSendingGroups(int $id, int $sys_dmail_uid, array $groups, string|int $userTable, $backendUserPermission): array
    {
//        $opt = [];
        $mailGroups = [];
        $lastGroup = null;
        if ($groups) {
            foreach ($groups as $group) {
                $result = static::compileMailGroup($group['uid'], $groups, $userTable, $backendUserPermission);
                $receiver = 0;
                $idLists = $result['queryInfo']['id_lists'];
                if (is_array($idLists['tt_address'] ?? false)) {
                    $receiver += count($idLists['tt_address']);
                }
                if (is_array($idLists['fe_users'] ?? false)) {
                    $receiver += count($idLists['fe_users']);
                }
                if (is_array($idLists['PLAINLIST'] ?? false)) {
                    $receiver += count($idLists['PLAINLIST']);
                }
                if (is_array($idLists[$userTable] ?? false)) {
                    $receiver += count($idLists[$userTable]);
                }
                $mailGroups[] = ['uid' => $group['uid'], 'title' => $group['title'], 'receiver' => $receiver];
                $lastGroup = $group;
            }
        }

        return $mailGroups;

//        $groupInput = '';
//        // added disabled. see hook
//        if (count($opt) === 0) {
//            $message = static::getFlashMessage(
//                static::getLL('error.no_recipient_groups_found'),
//                '',
//                AbstractMessage::ERROR
//            );
//            $this->messageQueue->addMessage($message);
//        } else if (count($opt) === 1) {
//            if (!$hookSelectDisabled) {
//                $groupInput .= '<input type="hidden" name="mailgroup_uid[]" value="' . $lastGroup['uid'] . '" />';
//            }
//            $groupInput .= '<ul><li>' . htmlentities($lastGroup['title']) . '</li></ul>';
//            if ($hookSelectDisabled) {
//                $groupInput .= '<em>disabled</em>';
//            }
//        } else {
//            $groupInput = '<select class="form-control" size="20" multiple="multiple" name="mailgroup_uid[]" ' . ($hookSelectDisabled ? 'disabled' : '') . '>' . implode(chr(10), $opt) . '</select>';
//        }
//
//        return [
//            'id' => $id,
//            'sys_dmail_uid' => $sys_dmail_uid,
//            'mailGroups' => $mailGroups,
//            'groupInput' => $groupInput,
//            'hookContents' => $hookContents, // put content from hook
//            'send_mail_datetime_hr' => strftime('%H:%M %d-%m-%Y', time()),
//            'send_mail_datetime' => strftime('%H:%M %d-%m-%Y', time()),
//        ];
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
