<?php

namespace MEDIAESSENZ\Mail\ViewHelpers;

use MEDIAESSENZ\Mail\Constants;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class RemoveMailBoundariesViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * @var bool
     */
    protected $escapeOutput = true;

    /**
     * @return string
     */
    public function render(): string
    {
        return preg_replace('/<!--' . Constants::CONTENT_SECTION_BOUNDARY .'_([\d,]*|END)-->/', '', $this->renderChildren());
    }
}
