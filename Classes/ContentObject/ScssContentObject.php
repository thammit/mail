<?php

namespace MEDIAESSENZ\Mail\ContentObject;

use MEDIAESSENZ\Mail\Parser\ScssParser;
use ScssPhp\ScssPhp\Exception\SassException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

if (!Environment::isComposerMode() && !class_exists(ScssParser::class)) {
    // @phpstan-ignore-next-line
    require_once 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

class ScssContentObject extends AbstractContentObject
{

    protected string $tempDirectory = 'typo3temp/assets/mail/css/';

    protected string $tempDirectoryRelativeToRoot = '../../../../';

    /**
     * @param array $conf
     * @return string
     * @throws SassException
     */
    public function render($conf = []): string
    {
        $file = $conf['file'];
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
                'tempDirectory' => $this->tempDirectory,
                'tempDirectoryRelativeToRoot' => $this->tempDirectoryRelativeToRoot,
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
            return file_get_contents(GeneralUtility::getFileAbsFileName($parser->compile($file, $settings)));
        } catch (\Exception $exception) {
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
            if (strpos($constant, $prefix) === 0) {
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
