<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ManipulateAddressRecipient
{
    /**
     * @throws InvalidQueryException
     */
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        if ($event->getRecipientSourceIdentifier() === 'tt_address') {
            $recipientSourceConfiguration = $event->getRecipientSourceConfiguration();
            $recipientData = $event->getRecipientData();
            if ($recipientSourceConfiguration->isModelSource() && ($recipientData['uid'] ?? false)) {
                // add all csv export field/values to existing data, but do not override already existing field/values!
                // this is important, because categories need to stay an array of uids
                $enhancedRecipientData = GeneralUtility::makeInstance(RecipientService::class)->getRecipientsDataByUidListAndModelName([$recipientData['uid']], $recipientSourceConfiguration->model, []);
                $recipientData += reset($enhancedRecipientData);
                $event->setRecipientData($recipientData);
            }
        }

    }
}
