<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\UserFunctions;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordTypesProcFunc
{
    /**
     * @throws SiteNotFoundException
     */
    public function itemsProcFunc(&$params): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($params['row']['pid'] ?? 0);
        $configuration = $site->getConfiguration()['mail'] ?? [];
        if ($configuration['recipientSources'] ?? false) {
            foreach ($configuration['recipientSources'] as $recipientSourceIdentifier => $recipientSource) {
                $params['items'][] = [$recipientSource['title'], $recipientSourceIdentifier, $recipientSource['icon']];
            }
        }
    }
}
