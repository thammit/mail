<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;

class SysLanguageRepository extends AbstractRepository {
    protected string $table = 'sys_language';
    
    /**
     * @throws Exception
     * @throws DBALException
     */
    public function selectSysLanguageForSelectCategories(string $lang, $sys_language, $static_languages): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('sys_language.uid')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'static_languages',
                'static_languages',
                $queryBuilder->expr()->eq('sys_language.language_isocode', $queryBuilder->quoteIdentifier('static_languages.lg_typo3'))
            )
            ->where(
                $queryBuilder->expr()->eq('static_languages.lg_typo3', $queryBuilder->createNamedParameter($lang . $sys_language . $static_languages))
            )
            ->execute()
            ->fetchAllAssociative();
    }
}
