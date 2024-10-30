<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use MEDIAESSENZ\Mail\Constants;
use TYPO3\CMS\Core\Core\Environment;

class ScssParserUtility
{
    /**
     * @return array
     */
    static public function deleteCacheFiles(): array
    {
        $path = Environment::getPublicPath() . '/' . trim(Constants::SCSS_PARSER_TEMP_DIR, '/');

        if (!is_dir($path)) {
            return [];
        }

        $files = scandir($path);
        $deletedFilePaths = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath)) {
                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

                if (in_array($fileExtension, ['css', 'meta'])) {
                    unlink($filePath);
                    $deletedFilePaths[] = $filePath;
                }
            }
        }

        return $deletedFilePaths;
    }
}
