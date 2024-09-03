<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Repository\GroupRepository;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class InvalidateCsvRecipients
{

    public function __construct(
        private GroupRepository $groupRepository,
        private PersistenceManager $persistenceManager
    )
    {
    }

    /**
     * @param DeactivateRecipientsEvent $disableRecipientsEvent
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
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
                if ($recipientSourceConfiguration->isCsv()) {
                    $group = $this->groupRepository->findByUid($recipientSourceConfiguration->groupUid);
                    if ($group instanceof Group) {
                        $recipients = $group->getCsvRecipients();
                        foreach ($recipients as &$recipient) {
                            // walk through all invalid recipients and make them (really) invalid in the recipient source
                            if (in_array($recipient['email'], $invalidRecipientEmails)) {
                                $recipient['email'] = RecipientUtility::invalidateEmail($recipient['email'], $data[$recipientSourceIdentifier]['returnCodes']);
                                $affectedRecipientsOfSource ++;
                            }
                        }
                        if ($affectedRecipientsOfSource) {
                            // write back changed emails
                            if ($group->isCsvFieldNames()) {
                                // add field names to recipients array
                                $recipients = array_merge([array_keys($recipients[0])], $recipients);
                            }

                            // build csv string
                            $csv = CsvUtility::arrayToCsv($recipients, $group->getCsvSeparatorString(), $group->getCsvEnclosureString());

                            if ($group->getCsvType() === CsvType::FILE) {
                                $file = $group->getCsvFile();
                                if ($file instanceof FileReference) {
                                    $file->getOriginalResource()->setContents($csv);
                                }
                            } else {
                                $group->setCsvData($csv);
                            }
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
