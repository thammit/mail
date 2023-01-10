<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\UserFunctions;

use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecipientSourcesProcFunc
{
    /**
     * @throws SiteNotFoundException
     */
    public function itemsProcFunc(&$params): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($params['row']['pid'] ?? 0);
        $recipientSources = $site->getConfiguration()['mail']['recipientSources'] ?? ConfigurationUtility::getDefaultRecipientSources() ?? [];
        if ($recipientSources) {
            foreach ($recipientSources as $recipientSourceIdentifier => $recipientSource) {
                $params['items'][] = [$recipientSource['title'], $recipientSourceIdentifier, $recipientSource['icon']];
            }
        }
    }
}
