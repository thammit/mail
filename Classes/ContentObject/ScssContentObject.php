<?php

namespace MEDIAESSENZ\Mail\ContentObject;

use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Parser\ScssParser;
use ScssPhp\ScssPhp\Exception\SassException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

class ScssContentObject extends AbstractContentObject
{
    /**
     * @param array $conf
     * @return string
     */
    public function render($conf = []): string
    {
        $file = $conf['file'];
        $cacheTags = $conf['cacheTags'] ?? '';
        if (!$file) {
            return '';
        }
        $absoluteFile = GeneralUtility::getFileAbsFileName($file);
        $fileInfo = pathinfo($absoluteFile);
        $settings = [
            'file' => [
                'absolute' => $absoluteFile,
                'relative' => $file,
                'info' => $fileInfo
            ],
            'cache' => [
                'tempDirectory' => Constants::SCSS_PARSER_TEMP_DIR,
                'tempDirectoryRelativeToRoot' => Constants::SCSS_PARSER_TEMP_DIR_RELATIVE_TO_ROOT,
                'tags' => $cacheTags
            ],
            'options' => [
                'override' => false,
                'sourceMap' => false,
                'compress' => true
            ],
            'variables' => $this->getVariablesFromConstants($fileInfo['extension'])
        ];

        try {
            $parser = GeneralUtility::makeInstance(ScssParser::class);
            if (!$parser->supports(pathinfo($absoluteFile)['extension'])) {
                return '';
            }
            $cacheFile = $parser->compile($file, $settings);
            return file_get_contents(GeneralUtility::getFileAbsFileName($cacheFile));
        } catch (\Exception|SassException $exception) {
            return '/* ERROR DURING SCSS COMPILATION: ' . $exception->getMessage() . ' */';
        }
    }

    /**
     * @param string $extension
     * @return array
     */
    protected function getVariablesFromConstants(string $extension = 'scss'): array
    {
        $constants = $this->getConstants();
        $extension = strtolower($extension);
        $variables = [];

        // Fetch settings
        $prefix = 'plugin.mail.settings.' . $extension . '.';
        foreach ($constants as $constant => $value) {
            if (str_starts_with($constant, $prefix)) {
                $variables[substr($constant, strlen($prefix))] = $value;
            }
        }

        return $variables;
    }

    /**
     * @return array
     */
    protected function getConstants(): array
    {
        if ($GLOBALS['TSFE']->tmpl->flatSetup === null
            || !is_array($GLOBALS['TSFE']->tmpl->flatSetup)
            || count($GLOBALS['TSFE']->tmpl->flatSetup) === 0) {
            $GLOBALS['TSFE']->tmpl->generateConfig();
        }
        return $GLOBALS['TSFE']->tmpl->flatSetup;
    }
}
