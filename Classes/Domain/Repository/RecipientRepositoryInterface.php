<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

interface RecipientRepositoryInterface
{
    public function findByUidListAndCategories(array $uidList, ObjectStorage $categories = null): QueryResultInterface|array;
}
