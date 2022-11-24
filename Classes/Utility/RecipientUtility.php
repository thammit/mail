<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecipientUtility
{
    /**
     * @param array $uidLists
     * @param string $userTable
     * @return int
     */
    public static function calculateTotalRecipientsOfUidLists(array $uidLists, string $userTable = ''): int
    {
        $totalRecipients = 0;
        if (is_array($uidLists['tt_address'] ?? false)) {
            $totalRecipients += count($uidLists['tt_address']);
        }
        if (is_array($uidLists['fe_users'] ?? false)) {
            $totalRecipients += count($uidLists['fe_users']);
        }
        if (is_array($uidLists['tx_mail_domain_model_group'] ?? false)) {
            $totalRecipients += count($uidLists['tx_mail_domain_model_group']);
        }
        if (is_array($uidLists[$userTable] ?? false)) {
            $totalRecipients += count($uidLists[$userTable]);
        }
        return $totalRecipients;
    }

    /**
     * Normalize a list of email addresses separated by colon, semicolon or enter (chr10) and remove not valid emails
     *
     * @param string $emailAddresses
     * @return string
     */
    public static function normalizeListOfEmailAddresses(string $emailAddresses): string
    {
        $addresses = preg_split('|[' . chr(10) . ',;]|', $emailAddresses);

        foreach ($addresses as $key => $val) {
            $addresses[$key] = trim($val);
            if (!GeneralUtility::validEmail($addresses[$key])) {
                unset($addresses[$key]);
            }
        }

        return implode(',', array_keys(array_flip($addresses)));
    }

    /**
     * Standard authentication code (used in Direct Mail, checkJumpUrl and setfixed links computations)
     *
     * @param int|array $uid_or_record Uid (int) or record (array)
     * @param string $fields List of fields from the record if that is given.
     * @param int $codeLength Length of returned authentication code.
     * @return string MD5 hash of 8 chars.
     */
    public static function stdAuthCode(int|array $uid_or_record, string $fields = '', int $codeLength = 8): string
    {
        if (is_array($uid_or_record)) {
            $recCopy_temp = [];
            if ($fields) {
                $fieldArr = GeneralUtility::trimExplode(',', $fields, true);
                foreach ($fieldArr as $k => $v) {
                    $recCopy_temp[$k] = $uid_or_record[$v];
                }
            } else {
                $recCopy_temp = $uid_or_record;
            }
            $preKey = implode('|', $recCopy_temp);
        } else {
            $preKey = $uid_or_record;
        }
        $authCode = $preKey . '||' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        return substr(md5($authCode), 0, $codeLength);
    }

    /**
     * Rearrange emails array into a 2-dimensional array
     *
     * @param array $plainMails Recipient emails
     *
     * @return array a 2-dimensional array consisting email and name
     */
    public static function reArrangePlainMails(array $plainMails): array
    {
        $out = [];
        $c = 0;
        foreach ($plainMails as $v) {
            $out[$c]['email'] = trim($v);
            $out[$c]['name'] = '';
            $c++;
        }
        return $out;
    }

    /**
     * Remove double record in an array
     *
     * @param array $plainList Email of the recipient
     *
     * @return array Cleaned array
     */
    public static function removeDuplicates(array $plainList): array
    {
        /*
         * $plainlist is a multidimensional array.
         * this method only remove if a value has the same array
         * $plainlist = [
         *        0 => [
         *            name => '',
         *            email => '',
         *        ],
         *        1 => [
         *            name => '',
         *            email => '',
         *        ],
         * ];
         */
        return array_map('unserialize', array_unique(array_map('serialize', $plainList)));
    }
}
