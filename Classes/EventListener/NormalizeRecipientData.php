<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Events\ManipulateRecipientEvent;

class NormalizeRecipientData
{
    public function __invoke(ManipulateRecipientEvent $event): void
    {
        $recipientData = $event->getRecipientData();
        $recipientTable = $event->getRecipientTable();

        if ($recipientTable === 'fe_users') {
            // fe_users use field 'telephone' for 'phone'
            if ($recipientData['telephone'] ?? false) {
                $recipientData['phone'] = $recipientData['telephone'];
            }
        }

        // Firstname must be more than 1 character
        $token = strtok(trim($recipientData['name']), ' ');
        $recipientData['firstname'] = $token ? trim($token) : '';

        if (strlen($recipientData['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['name'];
        }

        if (!trim($recipientData['firstname'])) {
            $recipientData['firstname'] = $recipientData['email'];
        }

        $event->setRecipientData($recipientData);
    }
}
