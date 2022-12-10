<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ManipulateFrontendUserRecipient
{
    /**
     * @throws InvalidQueryException
     */
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        if ($event->getRecipientSourceIdentifier() === 'fe_users') {
            $recipientSourceConfiguration = $event->getRecipientSourceConfiguration();
            $type = $recipientSourceConfiguration['type'] ?? false;
            $model = $recipientSourceConfiguration['model'] ?? false;
            $recipientData = $event->getRecipientData();
            if ($type === 'Extbase' && $model && ($recipientData['uid'] ?? false)) {
                // add all csv export field/values to existing data, but do not override already existing field/values!
                // this is important, because categories need to stay an array of uids
                $enhancedRecipientData = GeneralUtility::makeInstance(RecipientService::class)->getRecipientsDataByUidListAndModelName([$recipientData['uid']], $model, []);
                $recipientData += reset($enhancedRecipientData);
                // fe_users use field 'telephone' for 'phone'
                if ($recipientData['telephone'] ?? false) {
                    $recipientData['phone'] = $recipientData['telephone'];
                }
                $event->setRecipientData($recipientData);
            }
        }
    }
}
