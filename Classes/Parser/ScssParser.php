<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Parser;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\Version;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

if (!class_exists(Compiler::class) && !Environment::isComposerMode()) {
    // @phpstan-ignore-next-line
    @include 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

/**
 * ScssParser
 */
class ScssParser extends AbstractParser
{
    /**
     * @param string $extension
     * @return bool
     */
    public function supports(string $extension): bool
    {
        return $extension === 'scss';
    }

    /**
     * @param string $file
     * @param array $settings
     * @return string
     * @throws NoSuchCacheGroupException
     * @throws SassException
     */
    public function compile(string $file, array $settings): string
    {
        $cacheIdentifier = $this->getCacheIdentifier($file, $settings);
        if (!file_exists(Environment::getPublicPath() . '/' . $settings['cache']['tempDirectory'])) {
            GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/' . $settings['cache']['tempDirectory']);
        }
        $cacheFile = $this->getCacheFile($cacheIdentifier, $settings['cache']['tempDirectory']);
        $cacheFileMeta = $this->getCacheFileMeta($cacheFile);
        $compile = false;

        if (!$this->isCached($file, $settings)
            || $this->needsCompile($cacheFile, $cacheFileMeta, $settings)) {
            $compile = true;
        }

        if ($compile) {
            $result = $this->parseFile($file, $settings);
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($cacheFile), $result['css']);
            GeneralUtility::writeFile(GeneralUtility::getFileAbsFileName($cacheFileMeta), serialize($result['cache']));
            $this->clearPageCaches();
        }

        return $cacheFile;
    }

    /**
     * @param string $file
     * @param array $settings
     * @return array
     * @throws SassException
     */
    protected function parseFile(string $file, array $settings): array
    {
        $scss = new Compiler();
        $scss->setOutputStyle(OutputStyle::COMPRESSED);
        $scss->addVariables($settings['variables']);
        if ($settings['options']['sourceMap']) {
            $scss->setSourceMap(Compiler::SOURCE_MAP_INLINE);
            $scss->setSourceMapOptions([
                'sourceMapRootpath' => $settings['cache']['tempDirectoryRelativeToRoot'],
                'sourceMapBasepath' => '<PATH DOES NOT EXIST BUT SUPRESSES WARNINGS>'
            ]);
        }
        $absoluteFilename = $settings['file']['absolute'];
        // Adds visual directory path of the initial file as import path
        // This scenarios happens, when e.g. developing packages using the `path`
        // repository feature of Composer - having one package in `public/typo3conf/ext/`
        // and the other one symlinked in e.g. `packages/`.
        // Since the PHP SCSS parser works on resolved real paths, the symlinked context is lost.
        $visualImportPath = dirname($absoluteFilename);
        $scss->addImportPath(function ($url) use ($visualImportPath): ?string {
            // Resolve potential back paths manually using PathUtility::getCanonicalPath,
            // but make sure we do not break out of TYPO3 application path using GeneralUtility::getFileAbsFileName
            // Also resolve EXT: paths if given
            $isTypo3Absolute = str_starts_with($url, 'EXT:') || PathUtility::isAbsolutePath($url);
            $fileName = $isTypo3Absolute ? $url : $visualImportPath . '/' . $url;
            $full = GeneralUtility::getFileAbsFileName(PathUtility::getCanonicalPath($fileName));
            // The API forces us to check the existence of files paths, with or without url.
            // We must only return a string if the file to be imported actually exists.
            $hasExtension = (bool) preg_match('/[.]s?css$/', $url);
            if (
                is_file($file = $full . '.scss') ||
                ($hasExtension && is_file($file = $full))
            ) {
                // We could trigger a deprecation message here at some point
                return $file;
            }

            return null;
        });
        // Add extensions path to import paths, so that we can use paths relative to this directory to resolve imports
        $scss->addImportPath(Environment::getExtensionsPath());

        // Make paths in url() statements relative to site root
        $absoluteFilePath = dirname($absoluteFilename);
        $relativeFilePath = PathUtility::getAbsoluteWebPath($absoluteFilePath);
        $absoluteBootstrapPackageThemePath =
            ExtensionManagementUtility::extPath('mail') . 'Resources/Public/Scss';
        $relativeBootstrapPackageThemePath = PathUtility::getAbsoluteWebPath($absoluteBootstrapPackageThemePath);
        $scss->registerFunction(
            'url',
            function ($args) use (
                $scss,
                $absoluteFilePath,
                $relativeFilePath,
                $absoluteBootstrapPackageThemePath,
                $relativeBootstrapPackageThemePath
            ) : string {
                $marker = $args[0][1];
                $args[0][1] = '';
                $result = $scss->compileValue($args[0]);
                if (substr_compare($result, 'data:', 0, 5, true) !== 0) {
                    if (is_file(PathUtility::getCanonicalPath($absoluteFilePath . '/' . $result))) {
                        $result = PathUtility::getCanonicalPath($relativeFilePath . '/' . $result);
                    } elseif (is_file(PathUtility::getCanonicalPath($absoluteBootstrapPackageThemePath . '/' . $result))) {
                        $result = PathUtility::getCanonicalPath($relativeBootstrapPackageThemePath . '/' . $result);
                    }
                    $result = str_starts_with($result, '/') ? substr($result, 1) : $result;
                }
                return 'url(' . $marker . $result . $marker . ')';
            }
        );

        // Compile file
        $compilationResult = $scss->compileString('@import "' . $absoluteFilename . '"');
        $css = $compilationResult->getCss();

        // Fix paths in url() statements to be relative to temp directory
        $relativeTempPath = $settings['cache']['tempDirectoryRelativeToRoot'];
        $search = '%url\s*\(\s*[\\\'"]?(?!(((?:https?:)?\/\/)|(?:data:?:)))([^\\\'")]+)[\\\'"]?\s*\)%';
        $replace = 'url("' . $relativeTempPath . '$3")';
        $css = (string) preg_replace($search, $replace, $css);

        return [
            'css' => $css,
            'cache' => [
                'version' => Version::VERSION,
                'date' => date('r'),
                'css' => $css,
                'etag' => md5($css),
                'files' => $compilationResult->getIncludedFiles(),
                'variables' => $settings['variables'],
                'sourceMap' => $settings['options']['sourceMap']
            ]
        ];
    }
}
