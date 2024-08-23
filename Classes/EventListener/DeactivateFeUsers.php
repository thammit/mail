<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Domain\Model\FrontendUser;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Events\DeactivateRecipientsEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class DeactivateFeUsers
{
    private string $recipientSourceIdentifier = 'fe_users';

    public function __construct(
        private FrontendUserRepository $frontendUserRepository,
        private PersistenceManager $persistenceManager
    ) {
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
            switch (true) {
                case $recipientSourceConfiguration->isModelSource():
                    $frontendUser = $this->frontendUserRepository->findByUid((int)$recipient['uid']);
                    if ($frontendUser instanceof FrontendUser && $frontendUser->isActive()) {
                        $frontendUser->setActive(false);
                        $this->persistenceManager->update($frontendUser);
                        $this->persistenceManager->persistAll();
                        $affectedRecipients++;
                    }
                    break;
                case $recipientSourceConfiguration->isTableSource():
                    if ($recipient['mail_active']) {
                        $queryBuilder = $this->getQueryBuilder($recipientSourceConfiguration->table);
                        $affectedRecipients += $queryBuilder->update($recipientSourceConfiguration->table)
                            ->set('mail_active',0)
                            ->where($queryBuilder->expr()->eq('uid', $recipient['uid']))
                            ->executeStatement();
                    }
                    break;
            }
        }

        $disableRecipientsEvent->setNumberOfAffectedRecipients($affectedRecipients);
    }

    /*
     * D A T A B A S E
     */

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string|null $table
     * @return QueryBuilder
     */
    protected function getQueryBuilder(string $table = null): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table);
    }

    /**
     * @param string|null $table
     * @param bool $withDeleted
     * @return QueryBuilder
     */
    protected function getQueryBuilderWithoutRestrictions(string $table = null, bool $withDeleted = false): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        if (!$withDeleted) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }
        return $queryBuilder;
    }

}
