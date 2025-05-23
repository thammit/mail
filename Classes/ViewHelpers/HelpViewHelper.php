<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class HelpViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('table', 'string', 'Table', true);
        $this->registerArgument('field', 'string', 'Field', true);
    }

    public function render()
    {
        $table = $this->arguments['table'];
        $field = $this->arguments['field'];

        $helpTextArray = BackendUtility::helpTextArray($table, $field);
        $output = '';
        $arrow = '';
        // Put header before the rest of the text
        if ($helpTextArray['title'] !== null) {
            $output .= '<h2>' . $helpTextArray['title'] . '</h2>';
        }
        // Add see also arrow if we have more info
        if ($helpTextArray['moreInfo']) {
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $arrow = $iconFactory->getIcon('actions-view-go-forward', Icon::SIZE_SMALL)->render();
        }
        // Wrap description and arrow in p tag
        if ($helpTextArray['description'] !== null || $arrow) {
            $output .= '<p class="help-short">' . nl2br(htmlspecialchars((string)$helpTextArray['description'])) . $arrow . '</p>';
        }

        return $output;
    }
}
