<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail';

    /**
     * @param int $sys_dmail_uid
     * @param int $pid
     * @return array|bool
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailById(int $sys_dmail_uid, int $pid): array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($sys_dmail_uid, PDO::PARAM_INT))
            )
            //debug($queryBuilder->getSQL());
            //debug($queryBuilder->getParameters());
            ->execute()
            ->fetchAssociative();
    }

    /**
     * @param int $pid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailsByPid(int $pid): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('uid', 'pid', 'subject', 'scheduled', 'scheduled_begin', 'scheduled_end')
            ->from($this->table)
            ->add('where', 'pid = ' . intval($pid) . ' AND scheduled > 0')
            ->orderBy('scheduled', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $id
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectForPageInfo(int $id): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'COUNT(sys_dmail_maillog.mid) AS count')
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
     * @param int $id
     * @param string $sOrder
     * @param string $ascDesc
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectForMkeListDMail(int $id, string $sOrder, string $ascDesc): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('uid', 'pid', 'subject', 'tstamp', 'issent', 'renderedsize', 'attachment', 'type')
            ->from($this->table)
            ->add('where', 'pid = ' . intval($id) .
                ' AND scheduled=0 AND issent=0')
            ->orderBy($sOrder, $ascDesc)
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @param string $charset
     * @param string $mailContent
     * @return int
     * @throws DBALException
     */
    public function updateSysDmail(int $uid, string $charset, string $mailContent): int
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->update($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->set('issent', 0)
            ->set('charset', $charset)
            ->set('mailContent', $mailContent)
            ->set('renderedSize', strlen($mailContent))
            ->execute();
    }

    /**
     *
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function updateSysDmailRecord(int $uid, array $updateData): int
    {
        $connection = $this->getConnection($this->table);
        return $connection->update(
            $this->table, // table
            $updateData, // value array
            ['uid' => $uid]
        );
    }
}
