<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;

class NormalizeRecipientData
{
    public function __construct(private readonly RecipientService $recipientService)
    {
    }

    public function __invoke(ManipulateRecipientEvent $event): void
    {
        $recipientData = $event->getRecipientData();

        if (empty($recipientData['name'] ?? '')) {
            try {
                $additionalFields = ['first_name', 'middle_name', 'last_name'];
                $recipientSourceConfiguration = $event->getRecipientSourceConfiguration();

                switch (true) {
                    case $recipientSourceConfiguration->isModelSource():
                        $recipientUid = (int)$recipientData['uid'];
                        $enhancedRecipientData = $this->recipientService->getRecipientsDataByUidListAndModelName([$recipientUid],
                            $recipientSourceConfiguration->model, $additionalFields)[$recipientUid];
                        break;
                    case $recipientSourceConfiguration->isTableSource():
                        $recipientUid = (int)$recipientData['uid'];
                        $enhancedRecipientData = $this->recipientService->getRecipientsDataByUidListAndTable([$recipientUid],
                            $recipientSourceConfiguration->table, $additionalFields)[$recipientUid];
                        break;
                    case $recipientSourceConfiguration->isCsvOrPlain():
                        $enhancedRecipientData = $event->getRecipientData();
                }

                if (($enhancedRecipientData['first_name'] ?? false) || ($enhancedRecipientData['middle_name'] ?? false) || ($enhancedRecipientData['last_name'] ?? false)) {
                    $nameParts = [];
                    if ($enhancedRecipientData['first_name'] ?? false) {
                        $nameParts[] = trim($enhancedRecipientData['first_name']);
                    }
                    if ($enhancedRecipientData['middle_name'] ?? false) {
                        $nameParts[] = trim($enhancedRecipientData['middle_name']);
                    }
                    if ($enhancedRecipientData['last_name'] ?? false) {
                        $nameParts[] = trim($enhancedRecipientData['last_name']);
                    }
                    $recipientData['name'] = implode(' ', $nameParts);
                    $event->setRecipientData($recipientData);
                }
            } catch (\Exception $e) {
            }
        }
    }
}
