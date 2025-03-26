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
use TYPO3\CMS\Core\Information\Typo3Version;
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
        $this->registerArgument('uid', 'int', 'Page id of the Mail', true);
        $this->registerArgument('pageId', 'int', 'Page id of the PageTs configuration', true);
    }

    public function render()
    {
        $uid = (int)($this->arguments['uid'] ?? 0);
        try {
            $languages = LanguageUtility::getAvailablePageLanguages($uid);
        } catch (DBALException|Exception $e) {
            return [];
        }

        $pageTSConfiguration = TypoScriptUtility::implodeTSParams(BackendUtility::getPagesTSconfig($this->arguments['pageId'])['mod.']['web_modules.']['mail.'] ?? []);

        $previewHTMLLinkAttributes = [];
        $previewTextLinkAttributes = [];
        $htmlParams = trim(($pageTSConfiguration['htmlParams'] ?? ''), '&?');
        $plainParams = trim(($pageTSConfiguration['plainParams'] ?? ''), '&?');
        $isMultilingual = count($languages) > 1;

        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);

        foreach ($languages as $languageUid => $language) {

            $languageUid = (int)$languageUid;
            if ($languageUid === 0) {
                $page = $pageRepository->getPage($uid, true);
            } else {
                $page = $pageRepository->getPageOverlay($uid, $languageUid);
            }

            $title = htmlentities($page['title']);
            $flagIcon = $language['flagIcon'];
            $languageTitle = $isMultilingual ? ': ' . htmlentities($language['title']) : '';

            $previewHTMLLinkAttributes[$languageUid] = $this->getLinkAttributes($uid, $languageUid, $htmlParams, $title, $languageTitle, $flagIcon);
            $previewTextLinkAttributes[$languageUid] = $this->getLinkAttributes($uid, $languageUid, $plainParams, $title, $languageTitle, $flagIcon);
        }

        return match ($pageTSConfiguration['sendOptions'] ?? 0) {
            SendFormat::PLAIN => ['textPreview' => $previewTextLinkAttributes],
            SendFormat::HTML => ['htmlPreview' => $previewHTMLLinkAttributes],
            default => ['htmlPreview' => $previewHTMLLinkAttributes, 'textPreview' => $previewTextLinkAttributes],
        };
    }

    protected function getLinkAttributes(
        int $pageUid,
        int $languageUid,
        string $additionalQueryParameters,
        string $title,
        string $languageTitle,
        mixed $flagIcon,
    ): array {
        $previewUriBuilder = PreviewUriBuilder::create($pageUid);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            if ($languageUid > 0) {
                $additionalQueryParameters = rtrim('L=' . $languageUid . '&' . $additionalQueryParameters, '&');
            }
        } else {
            $previewUriBuilder = $previewUriBuilder->withLanguage($languageUid);
        }

        if ($additionalQueryParameters) {
            $previewUriBuilder = $previewUriBuilder->withAdditionalQueryParameters($additionalQueryParameters);
        }

        return [
            'title' => $title,
            'languageTitle' => $languageTitle,
            'uri' => $previewUriBuilder->buildUri(),
            'languageUid' => $languageUid,
            'flagIcon' => $flagIcon,
        ];
    }
}
