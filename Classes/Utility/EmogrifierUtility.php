<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Pelago\Emogrifier\CssInliner;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!Environment::isComposerMode() && !class_exists(CssInliner::class)) {
    // @phpstan-ignore-next-line
    require_once 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

class EmogrifierUtility
{
    /**
     * @param string $content
     * @param string $css
     * @param bool $extractContent
     * @param array $options
     * @return string
     * @throws ParseException
     */
    public static function emogrify($content, $css, $extractContent, $options = [])
    {
        if ($content !== null && $css !== null) {
            $cssInliner = CssInliner::fromHtml($content);
            if (!empty($options['disableStyleBlocksParsing'])) {
                $cssInliner = $cssInliner->disableStyleBlocksParsing();
            }
            if (!empty($options['disableInlineStyleAttributesParsing'])) {
                $cssInliner = $cssInliner->disableInlineStyleAttributesParsing();
            }
            if (!empty($options['addAllowedMediaType'])) {
                $cssInliner = $cssInliner->addAllowedMediaType($options['addAllowedMediaType']);
            }
            if (!empty($options['removeAllowedMediaType'])) {
                $cssInliner = $cssInliner->removeAllowedMediaType($options['removeAllowedMediaType']);
            }
            if (!empty($options['addExcludedSelector'])) {
                $cssInliner = $cssInliner->addExcludedSelector($options['addExcludedSelector']);
            }
            $content = $cssInliner->inlineCss($css)->render();

            if ($extractContent) {
                $content = preg_replace('/^.*<body[^>]*>(.*?)<\/body>.*$/sU', '$1', $content);
            }
        }

        return $content;
    }
}
