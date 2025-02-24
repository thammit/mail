<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class InlineCssViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('path', 'string', 'The path and filename of the resource file.', true);
        $this->registerArgument('addStyleTag', 'bool', 'Wrap css with style tags.', false, false);
    }

    public function render()
    {
        $path = GeneralUtility::getFileAbsFileName($this->arguments['path']);
        $css = GeneralUtility::getUrl($path);
        return ($this->arguments['addStyleTag'] ? '<style>' . $css . '</style>' : $css);
    }
}
