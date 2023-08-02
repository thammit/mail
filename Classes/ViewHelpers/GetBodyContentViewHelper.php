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
        $bodyDomNode = $document->getElementsByTagName('body')->item(0);
        if ($bodyHasStyle = $bodyDomNode->hasAttribute('style')) {
            $bodyContent .= '<div style="' . $bodyDomNode->getAttribute('style') . '">';
        }
        foreach ($bodyDomNode->childNodes as $node) {
            $bodyContent .= $document->saveHTML($node);
        }
        if ($bodyHasStyle) {
            $bodyContent .= '</div>';
        }

        return MailerUtility::removeDoubleBrTags($bodyContent);
    }
}
