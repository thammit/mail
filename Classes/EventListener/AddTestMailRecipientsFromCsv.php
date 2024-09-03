<?php
namespace Mail\Classes\EventListener;

use MEDIAESSENZ\Mail\Events\AddTestRecipientsEvent;

class AddTestMailRecipientsFromCsv
{
    public function __invoke(AddTestRecipientsEvent $event): void
    {
        $recipientSourceConfiguration = $event->getRecipientSourceConfiguration();

    }
}
