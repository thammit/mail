<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Service;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use MEDIAESSENZ\Mail\Enumeration\MailType;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailService
{
    /**
     * @var Mail|null
     */
    protected ?Mail $mail;

    public function __construct(protected LogRepository $logRepository)
    {
    }

    public function setMail(Mail $mail): void
    {
        $this->mail = $mail;
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function getMailInfo(): array
    {
        $dmailInfo = '';
        if ($this->mail->getType() === MailType::EXTERNAL) {
            $dmailData = $this->mail->getPlainParams() . ', ' . $this->mail->getHtmlParams();
        } else {
            $page = BackendUtility::getRecord('pages', $this->mail->getPage(), 'title');
            $dmailData = ' ' . $this->mail->getPage() . ', ' . htmlspecialchars($page['title']);
            $dmailInfo = TcaUtility::getTranslatedLabelOfTcaField('plain_params') . ' ' . htmlspecialchars($this->mail->getPlainParams() . LF . TcaUtility::getTranslatedLabelOfTcaField('html_params') . $this->mail->getHtmlParams()) . '; ' . LF;
        }

        $res = $this->logRepository->findAllByMailUid((int)$this->mail->getUid());

        $recipients = 0;
        $idLists = unserialize($this->mail->getQueryInfo());
        if (is_array($idLists)) {
            foreach ($idLists['id_lists'] as $idArray) {
                $recipients += count($idArray);
            }
        }

        return [
            'mail' => $this->mail,
            'dmailData' => $dmailData,
            'dmailInfo' => $dmailInfo,
            'type' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'type', $this->mail->getType()),
            'priority' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'priority', $this->mail->getPriority()),
            'sendOptions' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'send_options', $this->mail->getSendOptions()) . ($this->mail->getAttachment() ? '; ' : ''),
            'flowedFormat' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'flowed_format', $this->mail->isFlowedFormat()),
            'includeMedia' => BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'include_media', $this->mail->isIncludeMedia()),
            'recipients' => $recipients,
            'sentRecipients' => count($res),
        ];
    }

    /**
     *
     * @return array
     * @throws DBALException
     * @throws Exception
     */
    public function getGeneralInfo(): array
    {
        $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        $table = $sysDmailMaillogRepository->countSysDmailMaillogsResponseTypeByMid($this->mail->getUid());
        $table = $this->changekeyname($table, 'counter', 'COUNT(*)');

        // Plaintext/HTML
        $res = $sysDmailMaillogRepository->countSysDmailMaillogAllByMid($this->mail->getUid());

        /* this function is called to change the key from 'COUNT(*)' to 'counter' */
        $res = $this->changekeyname($res, 'counter', 'COUNT(*)');

        $textHtml = [];
        foreach ($res as $row2) {
            // 0:No mail; 1:HTML; 2:TEXT; 3:HTML+TEXT
            $textHtml[$row2['format_sent']] = $row2['counter'];
        }

        // Unique responses, html
        $uniqueHtmlResponses = $sysDmailMaillogRepository->countSysDmailMaillogHtmlByMid($this->mail->getUid());

        // Unique responses, Plain
        $uniquePlainResponses = $sysDmailMaillogRepository->countSysDmailMaillogPlainByMid($this->mail->getUid());

        // Unique responses, pings
        $uniquePingResponses = $sysDmailMaillogRepository->countSysDmailMaillogPingByMid($this->mail->getUid());

        $totalSent = (int)($textHtml['1'] ?? 0) + (int)($textHtml['2'] ?? 0) + (int)($textHtml['3'] ?? 0);
        $htmlSent = (int)($textHtml['1'] ?? 0) + (int)($textHtml['3'] ?? 0);
        $plainSent = (int)($textHtml['2'] ?? 0);

        return [
            'table' => [
                'head' => [
                    '',
                    'stats_total',
                    'stats_HTML',
                    'stats_plaintext',
                ],
                'body' => [
                    [
                        'stats_mails_sent',
                        $totalSent,
                        $htmlSent,
                        $plainSent,
                    ],
                    [
                        'stats_mails_returned',
                        $this->showWithPercent($table['-127']['counter'] ?? 0, $totalSent),
                        '',
                        '',
                    ],
                    [
                        'stats_HTML_mails_viewed',
                        '',
                        $this->showWithPercent($uniquePingResponses, $htmlSent),
                        '',
                    ],
                    [
                        'stats_unique_responses',
                        $this->showWithPercent($uniqueHtmlResponses + $uniquePlainResponses, $totalSent),
                        $this->showWithPercent($uniqueHtmlResponses, $htmlSent),
                        $this->showWithPercent($uniquePlainResponses, $plainSent ?: $htmlSent),
                    ],
                ],
            ],
            'uniqueHtmlResponses' => $uniqueHtmlResponses,
            'uniquePlainResponses' => $uniquePlainResponses,
            'totalSent' => $totalSent,
            'htmlSent' => $htmlSent,
            'plainSent' => $plainSent,
            'db' => $table,
        ];
    }

    /**
     * @throws Exception
     * @throws DBALException
     */
    public function getResponsesInfo(): array
    {
        $generalInfo = $this->getGeneralInfo();
        $uniqueHtmlResponses = $generalInfo['uniqueHtmlResponses'];
        $uniquePlainResponses = $generalInfo['uniquePlainResponses'];
        $totalSent = $generalInfo['totalSent'];
        $htmlSent = $generalInfo['htmlSent'];
        $plainSent = $generalInfo['plainSent'];
        $table = $generalInfo['db'];

        return [
            'head' => [
                '',
                'stats_total',
                'stats_HTML',
                'stats_plaintext',
            ],
            'body' => [
                [
                    'stats_total_responses',
                    ($table['1']['counter'] ?? 0) + ($table['2']['counter'] ?? 0),
                    $table['1']['counter'] ?? '0',
                    $table['2']['counter'] ?? '0',
                ],
                [
                    'stats_unique_responses',
                    $this->showWithPercent($uniqueHtmlResponses + $uniquePlainResponses, $totalSent),
                    $this->showWithPercent($uniqueHtmlResponses, $htmlSent),
                    $this->showWithPercent($uniquePlainResponses, $plainSent ?: $htmlSent),
                ],
                [
                    'stats_links_clicked_per_respondent',
                    ($uniqueHtmlResponses + $uniquePlainResponses ? number_format(($table['1']['counter'] + $table['2']['counter']) / ($uniqueHtmlResponses + $uniquePlainResponses),
                        2) : '-'),
                    ($uniqueHtmlResponses ? number_format(($table['1']['counter']) / ($uniqueHtmlResponses), 2) : '-'),
                    ($uniquePlainResponses ? number_format(($table['2']['counter']) / ($uniquePlainResponses), 2) : '-'),
                ],
            ],
        ];
    }

    // todo make static
    protected function showWithPercent(int $pieces, int $total): string
    {
        $total = intval($total);
        $str = $pieces ? number_format(intval($pieces)) : '0';
        if ($total) {
            $str .= ' / ' . number_format(($pieces / $total * 100), 2) . '%';
        }
        return $str;
    }

    /**
     * Switch the key of an array
     * todo make static
     *
     * @param array $array
     * @param string $newkey
     * @param string $oldkey
     * @return array $array
     */
    private function changekeyname(array $array, string $newkey, string $oldkey): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->changekeyname($value, $newkey, $oldkey);
            } else {
                $array[$newkey] = $array[$oldkey];
            }
        }
        unset($array[$oldkey]);
        return $array;
    }

}
