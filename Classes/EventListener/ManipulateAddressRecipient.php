<?php

namespace MEDIAESSENZ\Mail\EventListener;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ManipulateAddressRecipient
{
    /**
     * @throws InvalidQueryException
     * @throws Exception
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
            if ($recipientSourceConfiguration->isTableSource() && ($recipientData['uid'] ?? false) && !$recipientData['name']) {
                $addressRepository = GeneralUtility::makeInstance(AddressRepository::class);
                $address = $addressRepository->findRecordByUid((int)$recipientData['uid'])[0];
                if (($address['first_name'] ?? false) || ($address['middle_name'] ?? false) || ($address['last_name'] ?? false)) {
                    $recipientData['name'] = str_replace('  ', ' ', trim(($address['first_name'] ?? '') . ' ' . trim($address['middle_name'] ?? '') . ' ' . ($address['last_name'] ?? '')));
                    $event->setRecipientData($recipientData);
                }
            }
        }
        if ($event->getRecipientSourceConfiguration()->isCsv()) {
            // csv data
            $recipientData = $event->getRecipientData();
            if (!array_key_exists('name', $recipientData) && ($recipientData['last_name'] ?? false)) {
                $recipientData['name'] = $recipientData['last_name'];
                $event->setRecipientData($recipientData);
            }
        }
    }
}
