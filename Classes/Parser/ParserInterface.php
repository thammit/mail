<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Parser;

/**
 * ParserInterface
 */
interface ParserInterface
{
    /**
     * @param string $extension
     * @return bool
     */
    public function supports(string $extension): bool;

    /**
     * @param string $file
     * @param array $settings
     * @return string
     */
    public function compile(string $file, array $settings): string;
}
