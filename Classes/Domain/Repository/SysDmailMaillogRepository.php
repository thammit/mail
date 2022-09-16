<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailMaillogRepository extends AbstractRepository
{
    protected string $table = 'sys_dmail_maillog';

    /**
     * @param int $mid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogAllByMid(int $mid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->addSelect('html_sent')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->add('where', 'mid=' . $mid . ' AND response_type=0')
            ->groupBy('html_sent')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $mid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogHtmlByMid(int $mid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT))
            )
            ->groupBy('rid')
            ->addGroupBy('rtbl')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param int $mid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogPlainByMid(int $mid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(2, PDO::PARAM_INT))
            )
            ->groupBy('rid')
            ->addGroupBy('rtbl')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param int $mid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogPingByMid(int $mid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-1, PDO::PARAM_INT))
            )
            ->groupBy('rid')
            ->addGroupBy('rtbl')
            ->executeQuery()
            ->fetchOne();
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
     * @param int $uid
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    public function countByUid(int $uid): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('html_sent', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function countSysDmailMaillogsResponseTypeByMid(int $uid): array
    {
        $responseTypes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->groupBy('response_type')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $responseTypes[$row['response_type']] = $row;
        }

        return $responseTypes;
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectSysDmailMaillogsCompactView(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->orderBy('rid', 'ASC')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     *
     * @param int $uid
     * @param int $responseType 1 for html, 2 for plain
     * @return array
     * @throws Exception
     * @throws DBALException
     */
    public function findMostPopularLinks(int $uid, int $responseType = 1): array
    {
        $popularLinks = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('url_id')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
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
    public function countReturnCode(int $uid, int $responseType = -127): array
    {
        $returnCodes = [];
        $queryBuilder = $this->getQueryBuilder();

        $statement = $queryBuilder
            ->count('*')
            ->addSelect('return_code')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
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
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function selectStatTempTableContent(int $uid): array
    {
        return $this->getQueryBuilder()
            ->select('rid', 'rtbl', 'tstamp', 'response_type', 'url_id', 'html_sent', 'size')
            ->from($this->table)
            ->add('where', 'mid=' . $uid)
            ->orderBy('rtbl')
            ->addOrderBy('rid')
            ->addOrderBy('tstamp')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findAllReturnedMail(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findUnknownRecipient(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(550, PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(553, PDO::PARAM_INT))
                )
            )
//            ->add('where', 'mid=' . $uid .
//                ' AND response_type=-127' .
//                ' AND (return_code=550 OR return_code=553)')
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findMailboxFull(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(551, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findBadHost(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(552, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findBadHeader(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(554, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $uid
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function findUnknownReasons(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('rid', 'rtbl', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(-127, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('return_code', $queryBuilder->createNamedParameter(-1, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * @param int $rid
     * @param string $rtbl
     * @param int $mid
     * @return bool|array
     * @throws DBALException
     * @throws Exception
     */
    public function selectForAnalyzeBounceMail(int $rid, string $rtbl, int $mid): bool|array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid', 'email')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($rid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($rtbl)),
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, PDO::PARAM_INT)),
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        $statement = $queryBuilder
            ->select('rid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mailUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($recipientTable)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT))
            )
            ->execute();

        $list = [];

        while (($row = $statement->fetchAssociative())) {
            $list[] = $row['rid'];
        }

        return $list;
    }


    /**
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param string $rid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parseTime Parse time of the email
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return int
     * @throws DBALException
     */
    public function insertRecord(int $mid, string $rid, int $size, int $parseTime, int $html, string $email): int
    {
        [$rtbl, $rid] = explode('_', $rid);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->insert('sys_dmail_maillog')
            ->values([
                'mid' => $mid,
                'rtbl' => $rtbl,
                'rid' => $rid,
                'email' => $email,
                'tstamp' => time(),
                'url' => '',
                'size' => $size,
                'parsetime' => $parseTime,
                'html_sent' => $html,
            ])
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId($this->table);
    }

    /**
     * @param int $uid
     * @param int $size
     * @param int $parseTime
     * @param int $returnCode
     * @return int
     * @throws DBALException
     */
    public function updateRecord(int $uid, int $size, int $parseTime, int $returnCode): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->update('sys_dmail_maillog')
            ->set('tstamp', time())
            ->set('size', $size)
            ->set('parsetime', $parseTime)
            ->set('html_sent', $returnCode)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)))
            ->executeStatement();
    }

}
