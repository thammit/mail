<?php

namespace MEDIAESSENZ\Mail\EventListener;

use Doctrine\DBAL\Exception;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ManipulateCsvRecipient
{
    /**
     * @throws InvalidQueryException
     * @throws Exception
     */
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        if ($event->getRecipientSourceConfiguration()->isCsv()) {
            // csv data
            $recipientData = $event->getRecipientData();
            if (($recipientData['first_name'] ?? false) || ($recipientData['middle_name'] ?? false) || ($recipientData['last_name'] ?? false)) {
                $nameParts = [];
                if ($recipientData['first_name'] ?? false) {
                    $nameParts[] = trim($recipientData['first_name']);
                }
                if ($recipientData['middle_name'] ?? false) {
                    $nameParts[] = trim($recipientData['middle_name']);
                }
                if ($recipientData['last_name'] ?? false) {
                    $nameParts[] = trim($recipientData['last_name']);
                }
                $recipientData['name'] = implode(' ', $nameParts);
                $event->setRecipientData($recipientData);
            }
        }
    }
}
