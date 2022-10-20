<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class GroupRepository extends Repository
{
    public function initializeObject()
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
        $this->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
    }

    public function persist(): void
    {
        $this->persistenceManager->persistAll();
    }

    /**
     * @throws InvalidQueryException
     */
    public function findByUidList(array $uidList): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->in('uid', $uidList)
        );

        return $query->execute();
    }
}
