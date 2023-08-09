<?php

namespace MEDIAESSENZ\Mail\Hooks;

use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AddBackToMailWizardButton
{
    public function render($params, PageLayoutController $pObj): string
    {
        if ($GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_mail'] ?? false) {
            if ($mailId = $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_mail']['mail'] ?? null) {
                $params = [
                    'tx_mail_mailmail_mailmail' => [
                        'controller' => 'Mail',
                        'action' => $GLOBALS['TYPO3_REQUEST']->getQueryParams()['tx_mail']['action'],
                        'mail' => $mailId,
                    ],
                ];
            }
            $url = GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoutePath('/module/MailMail/MailMail', $params ?? []);
            $icon = GeneralUtility::makeInstance(IconFactory::class)->getIcon('actions-arrow-left', Icon::SIZE_SMALL);
            LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/BackendLayout.xlf');
            $buttonLabel = LanguageUtility::getLL('backend_layout.backbutton');
            return "<a href='{$url}' class='btn btn-default'>{$icon} {$buttonLabel}</a>";
        }
        return '';
    }
}
