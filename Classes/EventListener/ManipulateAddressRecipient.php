<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ManipulateAddressRecipient
{
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        $recipientTable = $event->getRecipientTable();

        if ($recipientTable === Address::class) {
            $recipientData = $event->getRecipientData();
            // add all csv export field/values to existing data, but do not override already existing field/values!
            // this is important, because categories need to stay an array of uids
            $enhancedRecipientData = GeneralUtility::makeInstance(RecipientService::class)->getRecipientsDataByUidListAndModelName([$recipientData['uid']], $recipientTable, []);
            $recipientData += reset($enhancedRecipientData);
            $event->setRecipientData($recipientData);
        }

    }
}
