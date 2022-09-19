<?php
namespace MEDIAESSENZ\Mail\UserFunc;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class BoundaryUserFunc
{
    public string $boundaryStartWrap = '<!--DMAILER_SECTION_BOUNDARY_ | -->';
    public string $boundaryEnd = '<!--DMAILER_SECTION_BOUNDARY_END-->';

    /**
     * @var ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.4/Deprecation-94956-PublicCObj.html
     *
     * @param ContentObjectRenderer $contentObjectRenderer
     */
    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    /**
     * This function wraps HTML comments around the content.
     * The comments contain the uids of assigned direct mail categories.
     * It is called as "USER_FUNC" from TS.
     *
     * @param string $content Incoming HTML code which will be wrapped
     * @param array|null $conf Pointer to the conf array (TS)
     *
     * @return    string        content of the email with dmail boundaries
     * @throws DBALException|Exception
     */
    public function insertContentBoundaries(string $content, ?array $conf = [])
    {
        if (isset($conf['useParentCObj']) && $conf['useParentCObj']) {
            $this->contentObjectRenderer = $conf['parentObj']->cObj;
        }

        // this check could probably be moved to TS
        if ($GLOBALS['TSFE']->config['config']['insertDmailerBoundaries']) {
            if ($content != '') {
                // setting the default
                $categoryList = '';
                if (intval($this->contentObjectRenderer->data['module_sys_dmail_category']) >= 1) {
                    // if content type "RECORDS" we have to strip off
                    // boundaries from indcluded records
                    if ($this->contentObjectRenderer->data['CType'] == 'shortcut') {
                        $content = $this->stripInnerBoundaries($content);
                    }

                    // get categories of tt_content element
                    $foreignTable = 'sys_dmail_category';
                    $select = "$foreignTable.uid";
                    $localTableUidList = intval($this->contentObjectRenderer->data['uid']);
                    $mmTable = 'sys_dmail_ttcontent_category_mm';
                    $orderBy = $foreignTable . '.uid';

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($foreignTable);
                    $statement = $queryBuilder
                        ->select($select)
                        ->from($foreignTable)
                        ->from($mmTable)
                        ->where(
                            $queryBuilder->expr()->eq(
                                $foreignTable . '.uid',
                                $mmTable . '.uid_foreign'
                            )
                        )
                        ->andWhere(
                            $queryBuilder->expr()->in(
                                $mmTable . '.uid_local',
                                $localTableUidList
                            )
                        )
                        ->orderBy($orderBy)
                        ->execute();

                    while ($row = $statement->fetchAssociative()) {
                        $categoryList .= $row['uid'] . ',';
                    }
                    $categoryList = rtrim($categoryList, ',');
                }
                // wrap boundaries around content
                $content = $this->contentObjectRenderer->wrap($categoryList, $this->boundaryStartWrap) . $content . $this->boundaryEnd;
            }
        }
        return $content;
    }

    /**
     * Remove boundaries from TYPO3 content
     *
     * @param string $content the content with boundaries in comment
     *
     * @return string the content without boundaries
     */
    public function stripInnerBoundaries(string $content): string
    {
        // only dummy code at the moment
        $searchString = $this->contentObjectRenderer->wrap('[\d,]*', $this->boundaryStartWrap);
        $content = preg_replace('/' . $searchString . '/', '', $content);
        $content = preg_replace('/' . $this->boundaryEnd . '/', '', $content);
        return $content;
    }

    /**
     * Breaking lines into fixed length lines, using GeneralUtility::breakLinesForEmail()
     *
     * @param string $content The string to break
     * @param array $conf Configuration options: linebreak, charWidth; stdWrap enabled
     *
     * @return string Processed string
     * @see GeneralUtility::breakLinesForEmail()
     */
    public function breakLines(string $content, array $conf)
    {
        $linebreak = $GLOBALS['TSFE']->cObj->stdWrap(($conf['linebreak'] ?: chr(32) . LF), $conf['linebreak.']);
        $charWidth = $GLOBALS['TSFE']->cObj->stdWrap(($conf['charWidth'] ? intval($conf['charWidth']) : 76), $conf['charWidth.']);

        return MailUtility::breakLinesForEmail($content, $linebreak, $charWidth);
    }

    /**
     * Inserting boundaries for each sitemap point.
     *
     * @param string $content The content string
     * @param array $conf The TS conf
     *
     * @return string $content: the string wrapped with boundaries
     * @throws DBALException|Exception
     */
    public function insertSitemapBoundaries(string $content, array $conf): string
    {
        $uid = $this->contentObjectRenderer->data['uid'];
        $content = '';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_ttcontent_category_mm');
        $categories = $queryBuilder
            ->select('*')
            ->from('sys_dmail_ttcontent_category_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    (int) $uid
                )
            )
            ->orderBy('sorting')
            ->execute()
            ->fetchAllAssociative();

        if (count($categories) > 0) {
            $categoryList = [];
            foreach ($categories as $category) {
                $categoryList[] = $category['uid_foreign'];
            }
            $content = '<!--DMAILER_SECTION_BOUNDARY_' . implode(',', $categoryList) . '-->|<!--DMAILER_SECTION_BOUNDARY_END-->';
        }

        return $content;
    }
}
