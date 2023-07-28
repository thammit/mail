<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use Masterminds\HTML5;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class GetBodyContentViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function render(): string
    {
        $content = $this->renderChildren();
        $parser = new HTML5();
        $document = $parser->loadHTML($content);
        $bodyContent = '';
        $bodyChildNodes = $document->getElementsByTagName('body')->item(0)->childNodes;

        foreach ($bodyChildNodes as $node) {
            $bodyContent .= $document->saveHTML($node);
        }

        return MailerUtility::removeDoubleBrTags($bodyContent);
    }
}
