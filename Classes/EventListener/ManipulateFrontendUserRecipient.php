<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ManipulateFrontendUserRecipient
{
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        $recipientTable = $event->getRecipientTable();
        $recipientData = $event->getRecipientData();

        if ($recipientTable === FrontendUser::class) {
            // add all csv export field/values to existing data, but do not override already existing field/values!
            // this is important, because categories need to stay an array of uids
            $enhancedRecipientData = GeneralUtility::makeInstance(RecipientService::class)->getRecipientsDataByUidListAndModelName([$recipientData['uid']], $recipientTable, []);
            $recipientData += reset($enhancedRecipientData);
        }

        if ($recipientTable === 'fe_users' || $recipientTable === FrontendUser::class) {
            // fe_users use field 'telephone' for 'phone'
            if ($recipientData['telephone'] ?? false) {
                $recipientData['phone'] = $recipientData['telephone'];
            }
        }

        $event->setRecipientData($recipientData);
    }
}
