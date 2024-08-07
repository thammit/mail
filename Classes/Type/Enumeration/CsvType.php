<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class CsvType extends Enumeration
{
    const PLAIN = 0;
    const FILE = 1;
}
