<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\Category;

interface CategoryInterface
{
    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage;
}
