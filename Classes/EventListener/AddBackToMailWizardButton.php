<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AddBackToMailWizardButton
{
    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        if ($returnPath = $request->getQueryParams()['mailReturnPath'] ?? false) {
            $icon = GeneralUtility::makeInstance(IconFactory::class)->getIcon('actions-view-go-back', Icon::SIZE_SMALL);
            LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/BackendLayout.xlf');
            $buttonLabel = LanguageUtility::getLL('backend_layout.back_to_mail_wizard', 'LLL:EXT:mail/Resources/Private/Language/BackendLayout.xlf:');
            $event->addHeaderContent('<a href="' . $returnPath . '" class="btn btn-default mb-3">' . $icon . ' ' . $buttonLabel . '</a>');
        }
    }
}
