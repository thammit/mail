<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;
use MEDIAESSENZ\Mail\Service\RecipientService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class ManipulateFrontendUserRecipient
{
    public function __construct(private readonly RecipientService $recipientService)
    {
    }
    /**
     * @throws InvalidQueryException
     */
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        if ($event->getRecipientSourceIdentifier() === 'fe_users') {
            $recipientData = $event->getRecipientData();
            if (($recipientData['uid'] ?? false) && empty($recipientData['phone'] ?? '')) {
                try {
                    $recipientUid = (int)$recipientData['uid'];
                    $additionalFields = ['telephone'];
                    $recipientSourceConfiguration = $event->getRecipientSourceConfiguration();
                    if ($recipientSourceConfiguration->isModelSource()) {
                        $enhancedRecipientData = $this->recipientService->getRecipientsDataByUidListAndModelName([$recipientUid],
                            $recipientSourceConfiguration->model, $additionalFields)[$recipientUid];
                    } else {
                        if ($recipientSourceConfiguration->isTableSource()) {
                            $enhancedRecipientData = $this->recipientService->getRecipientsDataByUidListAndTable([$recipientUid],
                                $recipientSourceConfiguration->table, $additionalFields)[$recipientUid];
                        }
                    }
                    if ($enhancedRecipientData['telephone'] ?? false) {
                        $recipientData['phone'] = trim($enhancedRecipientData['telephone']);
                    }
                    $event->setRecipientData($recipientData);
                } catch (\Exception $e) {
                }
            }
        }
    }
}
