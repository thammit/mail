<?php

namespace MEDIAESSENZ\Mail\Hooks;

use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AddBackToMailWizardButton
{
    public function render(): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        if ($returnPath = $request->getQueryParams()['mailReturnPath'] ?? false) {
            $icon = GeneralUtility::makeInstance(IconFactory::class)->getIcon('actions-view-go-back', Icon::SIZE_SMALL);
            LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/BackendLayout.xlf');
            $buttonLabel = LanguageUtility::getLL('backend_layout.back_to_mail_wizard', 'LLL:EXT:mail/Resources/Private/Language/BackendLayout.xlf:');
            return '<a href="' . $returnPath . '" class="btn btn-default mb-3" style="background-color:#037eab; color:#fff">' . $icon . ' ' . $buttonLabel . '</a>';
        }
        return '';
    }
}
