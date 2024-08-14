<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class RecipientSourceType extends Enumeration
{
    const TABLE = 0;
    const MODEL = 1;
    const PLAIN = 2;
    const CSV = 3;
    const CSVFILE = 4;
    const SERVICE = 5;
}
