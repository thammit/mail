<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class CsvSeparator extends Enumeration
{
    const COMMA = 0;
    const SEMICOLON = 1;
    const TAB= 3;
}
