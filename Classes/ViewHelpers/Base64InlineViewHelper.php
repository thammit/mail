<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class Base64InlineViewHelper extends AbstractViewHelper
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
    }

    public function render()
    {
        $path = GeneralUtility::getFileAbsFileName($this->arguments['path']);
        $content = GeneralUtility::getUrl($path);
        $contentType = mime_content_type($path);
        $base64EncodedContent = base64_encode($content);
        return 'data:' . $contentType . ';base64,' . $base64EncodedContent;
    }
}
