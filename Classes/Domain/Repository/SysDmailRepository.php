<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use PDO;

class SysDmailRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail';

    /**
     * @param int $uid
     * @return array|bool
     * @throws DBALException
     * @throws Exception
     */
    public function findById(int $uid): array|bool
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder->select('*')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->execute()
            ->fetchAssociative();
    }

    /**
     * @param int $pid
     * @param array $fields
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findScheduledByPid(int $pid, array $fields = ['uid', 'pid', 'subject', 'scheduled', 'scheduled_begin', 'scheduled_end', 'recipients', 'query_info']): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder->select(...$fields)
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('scheduled', 0),
            )
            ->orderBy('scheduled', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $pageId
     * @param string|null $orderBy
     * @param string|null $order
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findOpenMailsByPageId(int $pageId, string $orderBy = null, string $order = null): array
    {
        $orderBy = $orderBy ?? $this->getDefaultOrderBy();
        $order = $order ?? $this->getDefaultOrder();
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder
            ->select('uid', 'pid', 'subject', 'tstamp', 'issent', 'renderedsize', 'attachment', 'type')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('scheduled', 0),
                $queryBuilder->expr()->eq('issent', 0),
            )
            ->orderBy($orderBy, $order)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     * @throws DBALException
     * @throws Exception
     */
    public function findMailsToSend(): array|bool
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();
        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq('scheduled', 0),
                $queryBuilder->expr()->lt('scheduled', time()),
                $queryBuilder->expr()->eq('scheduled_end', 0),
                $queryBuilder->expr()->notIn('type', [MailType::DRAFT_INTERNAL, MailType::DRAFT_EXTERNAL])
            )
            ->orderBy('scheduled')
            ->execute()
            ->fetchAssociative();
    }

    /**
     * @param int $id
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectForPageInfo(int $id): array
    {
        $queryBuilder = $this->getQueryBuilderWithoutRestrictions();

        return $queryBuilder->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'sys_dmail.recipients', 'COUNT(sys_dmail_maillog.mid) AS count')
            ->from($this->table, $this->table)
            ->leftJoin(
                'sys_dmail',
                'sys_dmail_maillog',
                'sys_dmail_maillog',
                $queryBuilder->expr()->eq('sys_dmail.uid', $queryBuilder->quoteIdentifier('sys_dmail_maillog.mid'))
            )
            ->add('where', 'sys_dmail.pid = ' . $id .
                ' AND sys_dmail.type IN (0,1)' .
                ' AND sys_dmail.issent = 1' .
                ' AND sys_dmail_maillog.response_type = 0' .
                ' AND sys_dmail_maillog.html_sent > 0')
            ->groupBy('sys_dmail_maillog.mid')
            ->orderBy('sys_dmail.scheduled', 'DESC')
            ->addOrderBy('sys_dmail.scheduled_begin', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     *
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function update(int $uid, array $updateData): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->table, // table
            $updateData, // value array
            ['uid' => $uid]
        );
    }

    /**
     * @param int $uid
     * @return void
     */
    public function delete(int $uid): void
    {
        if ($GLOBALS['TCA'][$this->table]['ctrl']['delete']) {
            $this->update($uid, [$GLOBALS['TCA'][$this->table]['ctrl']['delete'] => 1]);
        }
    }
}
