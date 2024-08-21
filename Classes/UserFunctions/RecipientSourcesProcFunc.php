<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\UserFunctions;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecipientSourcesProcFunc
{
    /**
     * @throws SiteNotFoundException
     * @throws Exception
     */
    public function itemsProcFunc(&$params): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($params['row']['pid'] ?? 0);
        $recipientSources = ConfigurationUtility::getRecipientSources($site->getConfiguration());
        if ($recipientSources) {
            foreach ($recipientSources as $recipientSource) {
                if (!$recipientSource->isCsvOrPlain() && !$recipientSource->isCsvFile()) {
                    $params['items'][] = [$recipientSource->title, $recipientSource->identifier, $recipientSource->icon];
                }
//              To add csv and plain recipient groups as well uncomment:
//              if ($recipientSource->isCsvOrPlain() || $recipientSource->isCsvFile()) {
//                  /** @var BackendUserAuthentication $backendUser */
//                  $backendUser = $GLOBALS['BE_USER'];
//                  $page = BackendUtility::getRecord('pages', $recipientSource->pid);
//                  if ($page && $backendUser->doesUserHaveAccess($page, 1)) {
//                      $params['items'][] = [$recipientSource->title, $recipientSource->identifier, $recipientSource->icon];
//                  }
//              } else {
//                  $params['items'][] = [$recipientSource->title, $recipientSource->identifier, $recipientSource->icon];
//              }
            }
        }
    }
}
