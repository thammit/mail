<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class AddressRepository extends \FriendsOfTYPO3\TtAddress\Domain\Repository\AddressRepository implements RecipientRepositoryInterface
{
    use RepositoryTrait;
    protected string $table = 'tt_address';

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
            $constrains[] = $query->logicalOr(...$categoryConstrains);
        }
        $query->matching(
            $query->logicalAnd(...$constrains)
        );
        return $query->execute();
    }
}
