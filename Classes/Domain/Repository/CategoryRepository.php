<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class CategoryRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @throws InvalidQueryException
     */
    public function findByPidList(array $pidList): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->in('pid', $pidList)
        );

        return $query->execute();
    }

    /**
     * @throws InvalidQueryException
     */
    public function findByUids(array $categoryUids, array $orderings = []): array|QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);

        $query->matching($query->in('uid', $categoryUids));

        if (!empty($orderings)) {
            $query->setOrderings($orderings);
        }

        return $query->execute();
    }
}
