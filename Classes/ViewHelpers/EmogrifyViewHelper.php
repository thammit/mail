<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use MEDIAESSENZ\Mail\Utility\EmogrifierUtility;

class EmogrifyViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initialize arguments.
     *
     * @throws Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('css', 'string', 'CSS as a string.');
        $this->registerArgument('extractContent', 'bool', 'Extract emogrified content from within body tags.', false, false);
        $this->registerArgument('options', 'array', 'CSS inliner options.', false, []);
    }

    public function render()
    {
        $content = $this->renderChildren();
        $css = $this->arguments['css'];
        $extractContent = $this->arguments['extractContent'];
        $options = $this->arguments['options'];

        return EmogrifierUtility::emogrify($content, $css, $extractContent, $options);
    }
}
