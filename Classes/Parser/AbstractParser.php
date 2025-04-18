<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Parser;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AbstractParser
 */
abstract class AbstractParser implements ParserInterface
{
    /**
     * @param string $extension
     * @return bool
     */
    public function supports(string $extension): bool
    {
        return false;
    }

    /**
     * @param string $file
     * @param array $settings
     * @return string
     */
    public function compile(string $file, array $settings): string
    {
        return $file;
    }

    /**
     * @param string $file
     * @param array $settings
     * @return bool
     */
    protected function isCached(string $file, array $settings): bool
    {
        $cacheIdentifier = $this->getCacheIdentifier($file, $settings);
        $cacheFile = $this->getCacheFile($cacheIdentifier, $settings['cache']['tempDirectory']);
        $cacheFileMeta = $this->getCacheFileMeta($cacheFile);

        return file_exists($cacheFile) && file_exists($cacheFileMeta);
    }

    /**
     * @param string $cacheFile
     * @param string $cacheFileMeta
     * @param array $settings
     * @return bool
     */
    protected function needsCompile(string $cacheFile, string $cacheFileMeta, array $settings): bool
    {
        $needCompilation = false;
        $fileModificationTime = filemtime($cacheFile);
        $metadata = unserialize((string) file_get_contents($cacheFileMeta), ['allowed_classes' => false]);

        foreach ($metadata['files'] as $file) {
            if (filemtime($file) > $fileModificationTime) {
                $needCompilation = true;
                break;
            }
        }

        if (!$needCompilation && $settings['variables'] !== $metadata['variables']) {
            $needCompilation = true;
        }

        if (!$needCompilation && $settings['options']['sourceMap'] !== $metadata['sourceMap']) {
            $needCompilation = true;
        }

        if ($needCompilation) {
            unlink($cacheFile);
            unlink($cacheFileMeta);
        }

        return $needCompilation;
    }

    /**
     * @param string $cacheIdentifier
     * @param string $tempDirectory
     * @return string
     */
    protected function getCacheFile(string $cacheIdentifier, string $tempDirectory): string
    {
        return $tempDirectory . $cacheIdentifier . '.css';
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function getCacheFileMeta(string $filename)
    {
        return $filename . '.meta';
    }

    /**
     * @param string $file
     * @param array $settings
     * @return string
     */
    protected function getCacheIdentifier(string $file, array $settings): string
    {
        $hash = hash('sha256', md5($file) . serialize($settings));
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return basename($file, '.' . $extension) . '-' . $hash;
    }

    /**
     * @return string
     */
    protected function getPathSite(): string
    {
        return Environment::getPublicPath() . '/';
    }

    /**
     * Clear all page caches
     * @param string $cacheTags
     * @throws NoSuchCacheGroupException
     */
    protected function clearPageCaches(string $cacheTags = ''): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        if ($cacheTags) {
            $cacheManager->flushCachesInGroupByTags('pages', GeneralUtility::trimExplode(',', $cacheTags, true));
        } else {
            $cacheManager->flushCachesInGroup('pages');
        }
    }
}
