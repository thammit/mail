<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Use this ViewHelper to provide edit links to mail page records. The ViewHelper will
 * pass the uids of content elements to FormEngine.
 *
 * The uid must be given as a positive integer.
 *
 * Examples
 * ========
 *
 * Link to the mail page record-edit action passed to FormEngine::
 *
 *    <mail:editMailPageContent uid="42" returnUrl="foo/bar" />
 *
 * Output::
 *
 *    <a href="/typo3/record/edit?edit[tt_content][1,2,3,4]=edit&returnUrl=foo/bar">
 *        Edit record
 *    </a>
 *
 */
class EditMailPageContentViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerArgument('uid', 'int', 'uid of page to be edited', true);
        $this->registerArgument('returnUrl', 'string', 'return to this URL after closing the edit dialog', false, '');
    }

    /**
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function render(): string
    {
        if ($this->arguments['uid'] < 1) {
            throw new \InvalidArgumentException('Uid must be a positive integer, ' . $this->arguments['uid'] . ' given.', 1526127158);
        }
        if (empty($this->arguments['returnUrl'])) {
            $this->arguments['returnUrl'] = $this->renderingContext->getRequest()->getAttribute('normalizedParams')->getRequestUri();
        }

        $contentRepository = GeneralUtility::makeInstance(TtContentRepository::class);
        $contentRecords = $contentRepository->findRecordByPid($this->arguments['uid'], ['uid'], true);

        $contentRecordUids = [];
        foreach ($contentRecords as $contentRecord) {
            $contentRecordUids[] = $contentRecord['uid'];
        }

        $params = [
            'edit' => ['tt_content' => [implode(',', $contentRecordUids) => 'edit']],
            'returnUrl' => $this->arguments['returnUrl'],
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = (string)$uriBuilder->buildUriFromRoute('record_edit', $params);
        $this->tag->addAttribute('href', $uri);
        $this->tag->setContent($this->renderChildren());
        $this->tag->forceClosingTag(true);
        return $this->tag->render();
    }
}
