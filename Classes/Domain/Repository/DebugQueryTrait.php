<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbQueryParser;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

trait DebugQueryTrait
{
    /**
     * @param QueryBuilder|Query $query
     * @param string|null $title
     * @param bool $replaceParams if true replaces the params in SQL statement with values, otherwise dumps the array of params. @see self::renderDebug()
     *
     * @throws Exception
     */
    protected function debugQuery(QueryBuilder|Query $query, string $title = null, bool $replaceParams = true): string
    {
        if ($query instanceof QueryBuilder) {
            $sql = $query->getSQL();
            $params = $query->getParameters();
            return $this->renderDebug($sql, $params, $title, $replaceParams);
        } elseif ($query instanceof Query) {
            return $this->parseTheQuery($query, $title, $replaceParams);
        } else {
            throw new Exception('Unhandled type for SQL query, currently only TYPO3\CMS\Core\Database\Query\QueryBuilder | TYPO3\CMS\Extbase\Persistence\Generic\Query can be debugged with ' . static::getRepositoryClassName() . '::debugQuery() method.', 1596458998);
        }
    }

    /**
     * Parses query and displays debug
     *
     * @param QueryInterface $query Query
     * @param string|null    $title Optional title
     * @param bool           $replaceParams if true replaces the params in SQL statement with values, otherwise dumps the array of params. @see self::renderDebug()
     */
    private function parseTheQuery(QueryInterface $query, string $title = null, bool $replaceParams = true): string
    {
        /** @var Typo3DbQueryParser $queryParser */
        $queryParser = GeneralUtility::makeInstance(Typo3DbQueryParser::class);

        $sql = $queryParser->convertQueryToDoctrineQueryBuilder($query)->getSQL();
        $params = $queryParser->convertQueryToDoctrineQueryBuilder($query)->getParameters();
        return $this->renderDebug($sql, $params, $title, $replaceParams);
    }


    /**
     * Renders the output with DebuggerUtility::var_dump()
     *
     * @param string      $sql Generated SQL
     * @param array       $params Params' array
     * @param string|null $title Optional title for var_dump()
     * @param bool        $replaceParams if true replaces the params in SQL statement with values, otherwise dumps the array of params.
     */
    private function renderDebug(string $sql, array $params, string $title = null, bool $replaceParams = true): string
    {
        if ($replaceParams) {

            $search = array();
            $replace = array();
            foreach ($params as $k => $v) {
                $search[] = ':' . $k;
                $type = gettype($v);
                if ($type == 'integer') {
                    $replace[] = $v;
                } else {
                    $replace[] = '\'' . $v . '\'';
                }
            }
            $sql = str_replace($search, $replace, $sql);
            return DebuggerUtility::var_dump($sql, $title, 8, true, false, true);
        } else {
            return DebuggerUtility::var_dump(
                [
                    'SQL'        => $sql,
                    'Parameters' => $params
                ],
                $title, 8, true, false, true);
        }
    }
}
