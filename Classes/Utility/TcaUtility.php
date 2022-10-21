<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;

class TcaUtility
{
    public static function getDefaultSortByFromTca(string $table): string
    {
        return preg_replace(
            '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
            trim($GLOBALS['TCA'][$table]['ctrl']['default_sortby'])
        );
    }

    /**
     * Get translated label of table column
     * default table: tx_mail_domain_model_mail
     *
     * @param string $columnName
     * @param string $table
     * @return string The label
     */
    public static function getTranslatedLabelOfTcaField(string $columnName, string $table = 'tx_mail_domain_model_mail'): string
    {
        return stripslashes(LanguageUtility::getLanguageService()->sL(BackendUtility::getItemLabel($table, $columnName)));
    }
}
