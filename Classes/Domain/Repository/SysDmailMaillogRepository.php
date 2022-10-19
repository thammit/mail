<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;

class SysDmailMaillogRepository extends AbstractRepository
{
    protected string $table = 'tx_mail_domain_model_log';

    /**
     * @param int $mailUid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countByUid(int $mailUid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('format_sent', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param int $recipientUid
     * @param string $recipientTable
     * @param int $mailUid
     * @return bool|array
     * @throws DBALException
     * @throws Exception
     */
    public function selectForAnalyzeBounceMail(int $recipientUid, string $recipientTable, int $mailUid): bool|array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('recipient_uid', $queryBuilder->createNamedParameter($recipientUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_table', $queryBuilder->createNamedParameter($recipientTable)),
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute()
            ->fetchAssociative();
    }

    /**
     * Get array of recipient ids, which has been sent
     *
     * @param int $mailUid Newsletter ID. UID of the sys_dmail record
     * @param string $recipientTable Recipient table
     *
     * @return array list of recipients
     * @throws DBALException
     * @throws Exception
     */
    public function findSentMails(int $mailUid, string $recipientTable): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return array_column($queryBuilder
            ->select('recipient_uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('recipient_table', $queryBuilder->createNamedParameter($recipientTable)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative(), 'recipient_uid');
    }


    /**
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param string $recipientUid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parseTime Parse time of the email
     * @param int $formatSent Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return int
     * @throws DBALException
     */
    public function insertRecord(int $mid, string $recipientUid, int $size, int $parseTime, int $formatSent, string $email): int
    {
        [$recipientTable, $recipientUid] = explode('_', $recipientUid);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->insert('tx_mail_domain_model_log')
            ->values([
                'mail' => $mid,
                'recipient_table' => $recipientTable,
                'recipient_uid' => $recipientUid,
                'email' => $email,
                'tstamp' => time(),
                'url' => '',
                'size' => $size,
                'parse_time' => $parseTime,
                'format_sent' => $formatSent,
            ])
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId($this->table);
    }

    /**
     * @param int $uid
     * @param int $size
     * @param int $parseTime
     * @param int $formatSent
     * @return int
     * @throws DBALException
     */
    public function updateRecord(int $uid, int $size, int $parseTime, int $formatSent): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->update('tx_mail_domain_model_log')
            ->set('tstamp', time())
            ->set('size', $size)
            ->set('parse_time', $parseTime)
            ->set('format_sent', $formatSent)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->executeStatement();
    }

}
