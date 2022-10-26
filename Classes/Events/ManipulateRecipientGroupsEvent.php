<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Events;

class ManipulateRecipientGroupsEvent
{
    /**
     * @param AfterMailerInitializationEvent $event
     * @return void
     * todo
     */
    public function __invoke(AfterMailerInitializationEvent $event): void
    {
        $event->getMailer()->injectMailSettings(['transport' => 'null']);
        // Your code to manipulate the recipient groups
    }
}
