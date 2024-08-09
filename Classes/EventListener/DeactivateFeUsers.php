<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class DeactivateFeUsers
{
    private string $recipientSourceIdentifier = 'fe_users';

    public function __construct(
        private FrontendUserRepository $frontendUserRepository,
        private PersistenceManager $persistenceManager
    )
    {
    }

    /**
     * @throws UnknownObjectException
     */
    public function __invoke(DeactivateRecipientsEvent $disableRecipientsEvent): void
    {
        $affectedRecipients = $disableRecipientsEvent->getNumberOfAffectedRecipients();
        $recipients = $disableRecipientsEvent->getData()[$this->recipientSourceIdentifier] ?? [];
        $recipientSourceConfiguration = $disableRecipientsEvent->getRecipientSources()[$this->recipientSourceIdentifier];
        foreach ($recipients as $recipient) {
            if ($recipientSourceConfiguration->model) {
                $address = $this->frontendUserRepository->findByUid((int)$recipient['uid']);
                if ($address instanceof Address && $address->isActive()) {
                    $address->setActive(false);
                    $this->persistenceManager->update($address);
                    $affectedRecipients ++;
                }
            } else {
                if ($recipient['mail_active']) {
                    $affectedRecipients += $this->frontendUserRepository->updateRecord(['mail_active' => 0], ['uid' => (int)$recipient['uid']]);
                }
            }
        }

        $disableRecipientsEvent->setNumberOfAffectedRecipients($affectedRecipients);
    }
}
