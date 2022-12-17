<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\ViewHelpers;

use Closure;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class PreviewLinksViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    /**
     * Initialize the arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('data', 'array', 'Mail data', true);
        $this->registerArgument('pageId', 'int', 'Page id of the PageTs configuration', true);
    }

    /**
     * get country infos from a given ISO3
     *
     * @param array $arguments
     * @param Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return array
     */
    public static function renderStatic(
        array                     $arguments,
        Closure                   $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): array
    {
        $pageTSConfiguration = BackendUtility::getPagesTSconfig($arguments['pageId'])['mod.']['web_modules.']['mail.'] ?? [];
        $implodedParams = TypoScriptUtility::implodeTSParams($pageTSConfiguration);
        $row = $arguments['data'];
        try {
            $languages = LanguageUtility::getAvailablePageLanguages($row['uid']);
        } catch (DBALException|Exception $e) {
            return [];
        }
        $previewHTMLLinkAttributes = [];
        $previewTextLinkAttributes = [];
        $multilingual = count($languages) > 1;
        foreach ($languages as $languageUid => $lang) {
            $langParam = static::getLanguageParam($languageUid, $pageTSConfiguration);
            $langTitle = $multilingual ? ' - ' . $lang['title'] : '';
            $plainParams = $implodedParams['plainParams'] ?? $langParam;
            $htmlParams = $implodedParams['htmlParams'] ?? $langParam;
            $flagIcon = $lang['flagIcon'];

            $previewUriBuilder = PreviewUriBuilder::create($row['uid'], '')
                ->withRootLine(BackendUtility::BEgetRootLine($row['uid']));

            $previewHTMLLinkAttributes[$languageUid] = [
                'title' => htmlentities(LanguageUtility::getLL('mail.wizard.htmlPreviewLink.title') . $langTitle),
                'uri' => $previewUriBuilder->withAdditionalQueryParameters($htmlParams)->buildUri(),
                'languageUid' => $languageUid,
                'flagIcon' => $flagIcon,
            ];

            $previewTextLinkAttributes[$languageUid] = [
                'title' => htmlentities(LanguageUtility::getLL('mail.wizard.plainTextPreviewLink.title') . $langTitle),
                'uri' => $previewUriBuilder->withAdditionalQueryParameters($plainParams)->buildUri(),
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

    public static function getLanguageParam(int $sysLanguageUid, array $params): string
    {
        return $params['langParams.'][$sysLanguageUid] ?? '&L=' . $sysLanguageUid;
    }
}
