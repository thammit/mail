<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\Address;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class DeactivateAddresses
{
    private string $recipientSourceIdentifier = 'tt_address';

    public function __construct(
        private AddressRepository $addressRepository,
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
        $recipients = $disableRecipientsEvent->getData()[$this->recipientSourceIdentifier]['recipients'] ?? [];
        $recipientSourceConfiguration = $disableRecipientsEvent->getRecipientSources()[$this->recipientSourceIdentifier];
        foreach ($recipients as $recipient) {
            if ($recipientSourceConfiguration->model) {
                $address = $this->addressRepository->findByUid((int)$recipient['uid']);
                if ($address instanceof Address && $address->isActive()) {
                    $address->setActive(false);
                    $this->persistenceManager->update($address);
                    $affectedRecipients ++;
                }
            } else {
                if ($recipient['mail_active']) {
                    $affectedRecipients += $this->addressRepository->updateRecord(['mail_active' => 0], ['uid' => (int)$recipient['uid']]);
                }
            }
        }

        $disableRecipientsEvent->setNumberOfAffectedRecipients($affectedRecipients);
    }
}
