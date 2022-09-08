<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;

class TtContentCategoryMmRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_ttcontent_category_mm';

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectUidForeignByUid(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('uid_foreign')
            ->from('sys_dmail_ttcontent_category_mm')
            ->add('where', 'uid_local=' . $uid)
            ->execute()
            ->fetchAllAssociative();
    }
}
