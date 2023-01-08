<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;
use MEDIAESSENZ\Mail\Constants;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!Environment::isComposerMode() && !class_exists(Configuration::class)) {
    // @phpstan-ignore-next-line
    require_once 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

class CategoryCommentConverter implements ConverterInterface, ConfigurationAwareInterface
{
    protected Configuration $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        if ($this->shouldPreserve($element)) {
            return '<!--' . $element->getValue() . '-->';
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['#comment'];
    }

    private function shouldPreserve(ElementInterface $element): bool
    {
        $preserve = $this->config->getOption('preserve_category_comments');
        if ($preserve === true) {
            $value = \trim($element->getValue());
            $pattern = '/' . Constants::CONTENT_SECTION_BOUNDARY . '_(|END|[0-9,]+)?/';
            return (bool)preg_match($pattern, $value);
        }

        return false;
    }
}
