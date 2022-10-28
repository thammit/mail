<?php

namespace MEDIAESSENZ\Mail\UserFunc;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Repository\SysCategoryMmRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class BoundaryUserFunc
{
    public string $boundaryStartWrap = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_ | -->';
    public string $boundaryEnd = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';

    /**
     * @var ContentObjectRenderer
     */
    protected ContentObjectRenderer $contentObjectRenderer;

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
     * The comments contain the uids of assigned categories.
     * It is called as "USER_FUNC" from TS.
     *
     * @param string $content Incoming HTML code which will be wrapped
     * @param array|null $conf Pointer to the conf array (TS)
     *
     * @return    string        content of the email with dmail boundaries
     * @throws DBALException|Exception
     */
    public function insertContentBoundaries(string $content, ?array $conf = []): string
    {
        if (!($GLOBALS['TSFE']->config['config']['insertContentBoundaries'] ?? false) || empty($content) || empty($this->contentObjectRenderer->data['categories'])) {
            return $content;
        }

        if (isset($conf['useParentCObj']) && $conf['useParentCObj']) {
            $this->contentObjectRenderer = $conf['parentObj']->cObj;
        }

        // if content type is shortcut -> use boundaries from included records
        if ($this->contentObjectRenderer->data['CType'] == 'shortcut') {
            $content = $this->stripInnerBoundaries($content);
        }

        // get categories from tt_content element
        $sysCategoryMmRepository = GeneralUtility::makeInstance(SysCategoryMmRepository::class);
        $contentElementCategories = $sysCategoryMmRepository->findByUidForeignTableNameFieldName((int)$this->contentObjectRenderer->data['uid'], 'tt_content');

        $categoryList = [];
        foreach ($contentElementCategories as $contentElementCategory) {
            $categoryList[] = $contentElementCategory['uid_local'];
        }

        // wrap boundaries around content
        return $this->contentObjectRenderer->wrap(implode(',', $categoryList), $this->boundaryStartWrap) . $content . $this->boundaryEnd;
    }

    /**
     * Remove boundaries from TYPO3 content
     *
     * @param string $content the content with boundaries in comment
     *
     * @return string the content without boundaries
     */
    protected function stripInnerBoundaries(string $content): string
    {
        // only dummy code at the moment
        $searchString = $this->contentObjectRenderer->wrap('[\d,]*', $this->boundaryStartWrap);
        $content = preg_replace('/' . $searchString . '/', '', $content);
        return preg_replace('/' . $this->boundaryEnd . '/', '', $content);
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
        $content = '';
        $sysCategoryMmRepository = GeneralUtility::makeInstance(SysCategoryMmRepository::class);
        $categories = $sysCategoryMmRepository->findByUidForeignTableNameFieldName((int)$this->contentObjectRenderer->data['uid'], 'tt_content');

        if (count($categories) > 0) {
            $categoryList = [];
            foreach ($categories as $category) {
                $categoryList[] = $category['uid_local'];
            }
            $content = '<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_' . implode(',',
                    $categoryList) . '-->|<!--' . Constants::CONTENT_SECTION_BOUNDARY . '_END-->';
        }

        return $content;
    }
}
