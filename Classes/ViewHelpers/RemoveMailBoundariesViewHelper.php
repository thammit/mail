<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use MEDIAESSENZ\Mail\Constants;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class RemoveMailBoundariesViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = true;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public string $boundaryStartWrap = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_ | -->';
    public string $boundaryEnd = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';

    /**
     * @return string
     */
    public function render(): string
    {
        $content = $this->renderChildren();
        $searchString = $this->wrap('[\d,]*', $this->boundaryStartWrap);
        $content = preg_replace('/' . $searchString . '/', '', $content);
        return preg_replace('/' . $this->boundaryEnd . '/', '', $content);
    }

    public function wrap($content, $wrap, $char = '|')
    {
        if ($wrap) {
            $wrapArr = explode($char, $wrap);
            $content = trim($wrapArr[0] ?? '') . $content . trim($wrapArr[1] ?? '');
        }
        return $content;
    }
}
