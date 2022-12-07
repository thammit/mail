<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FrontendUserRepository extends Repository implements RecipientRepositoryInterface
{
    public function initializeObject()
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
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

    /**
     * @throws InvalidQueryException
     */
    public function findByUidListAndCategories(array $uidList, ObjectStorage $categories = null): QueryResultInterface
    {
        $query = $this->createQuery();
        $constrains = [
            $query->in('uid', $uidList)
        ];
        if ($categories instanceof ObjectStorage && $categories->count() > 0) {
            $categoryConstrains = [];
            foreach ($categories as $category) {
                $categoryConstrains[] = $query->logicalOr($query->contains('categories', $category->getUid()));
            }
            $constrains[] = $query->logicalOr($categoryConstrains);
        }
        $query->matching(
            $query->logicalAnd($constrains)
        );
        return $query->execute();
    }
}
