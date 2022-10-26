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

    public static function searchForHeaderData(Message $message, $header = Constants::MAIL_HEADER_IDENTIFIER): bool|array
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
     * Analyses the return-mail content for the Dmailer module
     * used to find what reason there was for rejecting the mail
     * Used by the Dmailer, but not exclusively.
     *
     * @param string $c Message Body/text
     *
     * @return array  key/value pairs with analysis result.
     *  Eg. "reason", "content", "reason_text", "mailserver" etc.
     */
    public static function analyseReturnError(string $c): array
    {
        $cp = [];
        // QMAIL
        if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '|' . preg_quote('------ This is a copy of the message, including all the headers.') . '/i', $c)) {
            if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '/i', $c)) {
                // Splits by the QMAIL divider
                $parts = explode('-- Below this line is a copy of the message.', $c, 2);
            } else {
                // Splits by the QMAIL divider
                $parts = explode('------ This is a copy of the message, including all the headers.', $c, 2);
            }
            $cp['content'] = trim($parts[0]);
            $parts = explode('>:', $cp['content'], 2);
            $cp['reason_text'] = trim($parts[1])?:$cp['content'];
            $cp['mailserver'] = 'Qmail';
            $cp['reason'] = self::extractReason($cp['reason_text']);
        } elseif (str_contains($c, 'The Postfix program')) {
            // Postfix
            $cp['content'] = trim($c);
            $parts = explode('>:', $c, 2);
            $cp['reason_text'] = trim($parts[1]);
            $cp['mailserver'] = 'Postfix';
            if (stristr($cp['reason_text'], '550')) {
                // 550 Invalid recipient, User unknown
                $cp['reason'] = 550;
            } elseif (stristr($cp['reason_text'], '553')) {
                // No such user
                $cp['reason'] = 553;
            } elseif (stristr($cp['reason_text'], '551')) {
                // Mailbox full
                $cp['reason'] = 551;
            } elseif (stristr($cp['reason_text'], 'recipient storage full')) {
                // Mailbox full
                $cp['reason'] = 551;
            } else {
                $cp['reason'] = -1;
            }
        } elseif (str_contains($c, 'Your message cannot be delivered to the following recipients:')) {
            // whoever this is...
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(strstr($cp['content'], 'Your message cannot be delivered to the following recipients:'));
            $cp['reason_text'] = trim(substr($cp['reason_text'], 0, 500));
            $cp['mailserver'] = 'unknown';
            $cp['reason'] = self::extractReason($cp['reason_text']);
        } elseif (str_contains($c, 'Diagnostic-Code: X-Notes')) {
            // Lotus Notes
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(strstr($cp['content'], 'Diagnostic-Code: X-Notes'));
            $cp['reason_text'] = trim(substr($cp['reason_text'], 0, 200));
            $cp['mailserver'] = 'Notes';
            $cp['reason'] = self::extractReason($cp['reason_text']);
        } else {
            // No-named:
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(substr($c, 0, 1000));
            $cp['mailserver'] = 'unknown';
            $cp['reason'] = self::extractReason($cp['reason_text']);
        }

        return $cp;
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
