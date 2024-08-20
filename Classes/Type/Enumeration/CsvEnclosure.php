<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class CsvEnclosure extends Enumeration
{
    const DOUBLE_QUOTE = 0;
    const SINGLE_QUOTE = 1;
    const BACK_TICK = 2;
}
