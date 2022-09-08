<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtility
{
    /**
     * @throws DBALException
     * @throws Exception
     */
    public static function getLocalizedCategories(array &$params): void
    {
        global $LANG;
        $uid = 0;
        $config = $params['config'];
        $table = $config['itemsProcFunc_config']['table'];

        // initialize backend user language
        if ($LANG->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $sysPage = GeneralUtility::makeInstance(PageRepository::class);

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_language');
            $res = $queryBuilder
                ->select('sys_language.uid')
                ->from('sys_language')
                ->leftJoin(
                    'sys_language',
                    'static_languages',
                    'static_languages',
                    $queryBuilder->expr()->eq('sys_language.language_isocode', $queryBuilder->quoteIdentifier('static_languages.lg_typo3'))
                )
                ->where(
                    $queryBuilder->expr()->eq('static_languages.lg_typo3', $queryBuilder->createNamedParameter($GLOBALS['LANG']->lang .
                        $sysPage->enableFields('sys_language') .
                        $sysPage->enableFields('static_languages')))
                )
                ->execute()
                ->fetchAllAssociative();
            foreach ($res as $row) {
                $uid = $row['uid'];
            }

        }

        if (is_array($params['items']) && !empty($params['items'])) {
            foreach ($params['items'] as $k => $item) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable($table);
                $res = $queryBuilder
                    ->select('*')
                    ->from($table)
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(intval($item[1])))
                    )
                    ->execute()
                    ->fetchAllAssociative();
                foreach ($res as $rowCat) {
                    if (($localizedRowCat = MailUtility::getRecordOverlay($table, $rowCat, $uid, ''))) {
                        $params['items'][$k][0] = $localizedRowCat['category'];
                    }
                }

            }
        }
    }
}
