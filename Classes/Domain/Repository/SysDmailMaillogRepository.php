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
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogAllByMid(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->addSelect('format_sent')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', 0),

            )
            ->groupBy('format_sent')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogHtmlByMid(int $mailUid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return count($queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', 1)
            )
            ->groupBy('recipient_uid')
            ->addGroupBy('recipient_table')
            ->orderBy('COUNT(*)')
            ->execute()
            ->fetchAllAssociative());
    }

    /**
     * @param int $mailUid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogPlainByMid(int $mailUid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return count($queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', 2)
            )
            ->groupBy('recipient_uid')
            ->addGroupBy('recipient_table')
            ->orderBy('COUNT(*)')
            ->execute()
            ->fetchAllAssociative());
    }

    /**
     * @param int $mailUid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogPingByMid(int $mailUid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return count($queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', -1)
            )
            ->groupBy('recipient_uid')
            ->addGroupBy('recipient_table')
            ->orderBy('COUNT(*)')
            ->execute()
            ->fetchAllAssociative());
    }

    /**
     * @param int $responseType
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findByResponseType(int $responseType): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid', 'tstamp')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, PDO::PARAM_INT)))
            ->orderBy('tstamp', 'DESC')
            ->execute()
            ->fetchAllAssociative();
    }

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
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogsResponseTypeByMid(int $mailUid): array
    {
        $responseTypes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)))
            ->groupBy('response_type')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $responseTypes[$row['response_type']] = $row;
        }

        return $responseTypes;
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailMaillogsCompactView(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->orderBy('recipient_uid', 'ASC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     *
     * @param int $mailUid
     * @param int $responseType 1 for html, 2 for plain
     * @return array
     * @throws Exception
     * @throws DBALException
     */
    public function findMostPopularLinks(int $mailUid, int $responseType = 1): array
    {
        $popularLinks = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('url_id')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, PDO::PARAM_INT))
            )
            ->groupBy('url_id')
            ->orderBy('COUNT(*)')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $popularLinks[$row['url_id']] = $row;
        }
        return $popularLinks;
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function countReturnCode(int $mailUid, int $responseType = -127): array
    {
        $returnCodes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('return_code')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, PDO::PARAM_INT))
            )
            ->groupBy('return_code')
            ->orderBy('COUNT(*)')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $returnCodes[$row['return_code']] = $row;
        }

        return $returnCodes;
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectStatTempTableContent(int $mailUid): array
    {
        return $this->getQueryBuilder()
            ->select('recipient_uid', 'recipient_table', 'tstamp', 'response_type', 'url_id', 'format_sent', 'size')
            ->from($this->table)
            ->where($this->getQueryBuilder()->expr()->eq('mail', $mailUid))
            ->orderBy('recipient_table')
            ->addOrderBy('recipient_uid')
            ->addOrderBy('tstamp')
            ->execute()
            ->fetchAllAssociative();
    }

    private function getIdLists(array $rrows): array
    {
        $idLists = [
            'tt_address' => [],
            'fe_users' => [],
            'PLAINLIST' => [],
        ];

        foreach ($rrows as $rrow) {
            switch ($rrow['recipient_table']) {
                case 't':
                    $idLists['tt_address'][] = $rrow['recipient_uid'];
                    break;
                case 'f':
                    $idLists['fe_users'][] = $rrow['recipient_uid'];
                    break;
                case 'P':
                    $idLists['PLAINLIST'][] = $rrow['email'];
                    break;
                default:
                    $idLists[$rrow['recipient_table']][] = $rrow['recipient_uid'];
            }
        }

        return $idLists;
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findAllReturnedMail(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findUnknownRecipient(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(550, PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(553, PDO::PARAM_INT))
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findMailboxFull(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(551, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findBadHost(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(552, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findBadHeader(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(554, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mailUid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findUnknownReasons(int $mailUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('recipient_uid', 'recipient_table', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mail', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(-1, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
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
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return int
     * @throws DBALException
     */
    public function insertRecord(int $mid, string $recipientUid, int $size, int $parseTime, int $html, string $email): int
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
                'format_sent' => $html,
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
