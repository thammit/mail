<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class MailRepository extends Repository
{
    /**
     * @param int $pid
     * @return object[]|QueryResultInterface
     */
    public function findOpenByPid(int $pid): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->logicalAnd([
                $query->equals('scheduled', 0),
                $query->equals('sent', 0),
                $query->equals('pid', $pid),
            ])
        );
        return $query->execute();
    }

    /**
     * @param int $pid
     * @param int $page
     * @return object[]|QueryResultInterface
     */
    public function findOpenByPidAndPage(int $pid, int $page): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->logicalAnd([
                $query->equals('scheduled', 0),
                $query->equals('sent', 0),
                $query->equals('page', $page),
                $query->equals('pid', $pid),
            ])
        );
        return $query->execute();
    }
}
