<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class PreviewLinksViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('uid', 'int', 'Mail uid', true);
        $this->registerArgument('pageId', 'int', 'Page id of the PageTs configuration', true);
    }

    public function render()
    {
        $uid = $this->arguments['uid'];
        try {
            $languages = LanguageUtility::getAvailablePageLanguages($uid);
        } catch (DBALException|Exception $e) {
            return [];
        }

        $pageTSConfiguration = BackendUtility::getPagesTSconfig($this->arguments['pageId'])['mod.']['web_modules.']['mail.'] ?? [];
        $implodedParams = TypoScriptUtility::implodeTSParams($pageTSConfiguration);
        $previewHTMLLinkAttributes = [];
        $previewTextLinkAttributes = [];
        $multilingual = count($languages) > 1;
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);

        foreach ($languages as $languageUid => $language) {

            if ($languageUid === 0) {
                $page = $pageRepository->getPage($uid, true);
            } else {
                $page = $pageRepository->getPageOverlay($uid, $languageUid);
            }

            $title = htmlentities($page['title']);
            $flagIcon = $language['flagIcon'];
            $languageTitle = $multilingual ? ': ' . htmlentities($language['title']) : '';

            $htmlPreviewUriBuilder = PreviewUriBuilder::create($uid)->withLanguage($languageUid);

            if ($implodedParams['htmlParams'] ?? false) {
                $htmlPreviewUriBuilder = $htmlPreviewUriBuilder->withAdditionalQueryParameters(trim($implodedParams['htmlParams'], '&?'));
            }

            $previewHTMLLinkAttributes[$languageUid] = [
                'title' => $title,
                'languageTitle' => $languageTitle,
                'uri' => $htmlPreviewUriBuilder->buildUri(),
                'languageUid' => $languageUid,
                'flagIcon' => $flagIcon,
            ];

            $plainPreviewUriBuilder = PreviewUriBuilder::create($uid)->withLanguage($languageUid);

            if ($implodedParams['plainParams'] ?? false) {
                $plainPreviewUriBuilder = $plainPreviewUriBuilder->withAdditionalQueryParameters(trim($implodedParams['plainParams'], '&?'));
            }

            $previewTextLinkAttributes[$languageUid] = [
                'title' => $title,
                'languageTitle' => $languageTitle,
                'uri' => $plainPreviewUriBuilder->buildUri(),
                'languageUid' => $languageUid,
                'flagIcon' => $flagIcon,
            ];
        }

        return match ($pageTSConfiguration['sendOptions'] ?? 0) {
            SendFormat::PLAIN => ['textPreview' => $previewTextLinkAttributes],
            SendFormat::HTML => ['htmlPreview' => $previewHTMLLinkAttributes],
            default => ['htmlPreview' => $previewHTMLLinkAttributes, 'textPreview' => $previewTextLinkAttributes],
        };
    }
}
