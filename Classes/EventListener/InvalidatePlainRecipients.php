<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class InvalidatePlainRecipients
{

    public function __construct(
        private GroupRepository $groupRepository,
        private PersistenceManager $persistenceManager
    )
    {
    }

    /**
     * @param DeactivateRecipientsEvent $disableRecipientsEvent
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function __invoke(DeactivateRecipientsEvent $disableRecipientsEvent): void
    {
        $affectedRecipients = $disableRecipientsEvent->getNumberOfAffectedRecipients();
        $data = $disableRecipientsEvent->getData();
        $recipientSourceIdentifiers = array_keys($data);
        foreach ($recipientSourceIdentifiers as $recipientSourceIdentifier) {
            $affectedRecipientsOfSource = 0;
            $invalidRecipientEmails = $data[$recipientSourceIdentifier]['recipients'] ?? [];
            if ($invalidRecipientEmails) {
                $recipientSourceConfiguration = $disableRecipientsEvent->getRecipientSources()[$recipientSourceIdentifier];
                if ($recipientSourceConfiguration->isPlain()) {
                    $group = $this->groupRepository->findByUid($recipientSourceConfiguration->groupUid);
                    if ($group instanceof Group) {
                        $recipients = $group->getListRecipients();
                        foreach ($recipients as &$recipient) {
                            // walk through all invalid recipients and make them (really) invalid in the recipient source
                            if (in_array($recipient['email'], $invalidRecipientEmails)) {
                                $recipient['email'] = RecipientUtility::invalidateEmail($recipient['email'], $data[$recipientSourceIdentifier]['returnCodes']);
                                $affectedRecipientsOfSource ++;
                            }
                        }
                        if ($affectedRecipientsOfSource) {
                            // write back changed emails
                            $group->setList(implode(LF, array_column($recipients, 'email')));
                            $this->groupRepository->update($group);
                            $this->persistenceManager->persistAll();
                        }
                    }
                }
            }
            $affectedRecipients += $affectedRecipientsOfSource;
        }
        $disableRecipientsEvent->setNumberOfAffectedRecipients($affectedRecipients);
    }
}
