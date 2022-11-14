<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Fetch\Message;
use MEDIAESSENZ\Mail\Constants;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BounceMailUtility
{
    const REASONS = [
        '550' => 'no mailbox|account does not exist|user unknown|Recipient unknown|recipient unknown|account that you tried to reach is disabled|User Unknown|User unknown|unknown in relay recipient table|user is unknown|unknown user|unknown local part|unrouteable address|does not have an account here|no such user|user not listed|account has been disabled or discontinued|user disabled|unknown recipient|invalid recipient|recipient problem|recipient name is not recognized|mailbox unavailable|550 5\.1\.1 recipient|status: 5\.1\.1|delivery failed 550|550 requested action not taken|receiver not found|unknown or illegal alias|is unknown at host|is not a valid mailbox|no mailbox here by that name|we do not relay|5\.7\.1 unable to relay|cuenta no activa|inactive user|user is inactive|mailaddress is administratively disabled|not found in directory|not listed in public name & address book|destination addresses were unknown|recipient address rejected|Recipient address rejected|Address rejected|rejected address|not listed in domino directory|domino directory entry does not|550-5\.1.1 The email account that you tried to reach does not exist|The email address you entered couldn',
        '551' => 'over quota|quota exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno|user mailbox exceeds allowed size',
        '552' => 'connection refused|Connection refused|connection timed out|Connection timed out|timed out while|Host not found|host not found|Unable to connect to DNS|t find any host named|unrouteable mail domain|not reached for any host after a long failure period|domain invalid|host lookup did not complete: retry timeout exceeded|no es posible conectar correctamente',
        '554' => 'error in header|header error|invalid message|invalid structure|header line format error'
    ];

    public static function getMailDataFromHeader(Message $message, $header = Constants::MAIL_HEADER_IDENTIFIER): bool|array
    {
        // get attachment
        $attachments = $message->getAttachments();
        $midArray = [];
        if (is_array($attachments)) {
            // search in attachment
            foreach ($attachments as $attachment) {
                // Find mail id
                $midArray = MailerUtility::decodeMailIdentifierHeader($attachment->getData(), $header);
                if ($midArray) {
                    // if mid, rid and rtbl are found, then stop looping
                    break;
                }
            }
        } else {
            // search in MessageBody (see rfc822-headers as Attachments placed )
            $midArray = MailerUtility::decodeMailIdentifierHeader($message->getMessageBody(), $header);
        }

        return $midArray;
    }

    /**
     * Analyse returned mail content to find reason why it was rejected
     *
     * @param string $content mail content
     *
     * @return array  key/value pairs with analyse result
     * e.g. "reason", "content", "reason_text", "mailserver" etc.
     */
    public static function analyseReturnError(string $content): array
    {
        $result = [];
        // QMAIL
        if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '|' . preg_quote('------ This is a copy of the message, including all the headers.') . '/i', $content)) {
            if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '/i', $content)) {
                // Splits by the QMAIL divider
                $parts = explode('-- Below this line is a copy of the message.', $content, 2);
            } else {
                // Splits by the QMAIL divider
                $parts = explode('------ This is a copy of the message, including all the headers.', $content, 2);
            }
            $result['content'] = trim($parts[0]);
            $parts = explode('>:', $result['content'], 2);
            $result['reason_text'] = trim($parts[1])?:$result['content'];
            $result['mailserver'] = 'Qmail';
            $result['reason'] = self::extractReason($result['reason_text']);
        } elseif (str_contains($content, 'The Postfix program')) {
            // Postfix
            $result['content'] = trim($content);
            $parts = explode('>:', $content, 2);
            $result['reason_text'] = trim($parts[1]);
            $result['mailserver'] = 'Postfix';
            if (stristr($result['reason_text'], '550')) {
                // 550 Invalid recipient, User unknown
                $result['reason'] = 550;
            } elseif (stristr($result['reason_text'], '553')) {
                // No such user
                $result['reason'] = 553;
            } elseif (stristr($result['reason_text'], '551')) {
                // Mailbox full
                $result['reason'] = 551;
            } elseif (stristr($result['reason_text'], 'recipient storage full')) {
                // Mailbox full
                $result['reason'] = 551;
            } else {
                $result['reason'] = -1;
            }
        } elseif (str_contains($content, 'Your message cannot be delivered to the following recipients:')) {
            // whoever this is...
            $result['content'] = trim($content);
            $result['reason_text'] = trim(strstr($result['content'], 'Your message cannot be delivered to the following recipients:'));
            $result['reason_text'] = trim(substr($result['reason_text'], 0, 500));
            $result['mailserver'] = 'unknown';
            $result['reason'] = self::extractReason($result['reason_text']);
        } elseif (str_contains($content, 'Diagnostic-Code: X-Notes')) {
            // Lotus Notes
            $result['content'] = trim($content);
            $result['reason_text'] = trim(strstr($result['content'], 'Diagnostic-Code: X-Notes'));
            $result['reason_text'] = trim(substr($result['reason_text'], 0, 200));
            $result['mailserver'] = 'Notes';
            $result['reason'] = self::extractReason($result['reason_text']);
        } else {
            // No-named:
            $result['content'] = trim($content);
            $result['reason_text'] = trim(substr($content, 0, 1000));
            $result['mailserver'] = 'unknown';
            $result['reason'] = self::extractReason($result['reason_text']);
        }

        return $result;
    }

    /**
     * Try to match reason found in the returned email
     * with the defined reasons (see $reason_text)
     *
     * @param string $text Content of the returned email
     *
     * @return int  The error code.
     */
    public static function extractReason(string $text): int
    {
        $reason = -1;
        foreach (self::REASONS as $case => $value) {
            if (preg_match('/' . $value . '/i', $text)) {
                return intval($case);
            }
        }
        return $reason;
    }
}
