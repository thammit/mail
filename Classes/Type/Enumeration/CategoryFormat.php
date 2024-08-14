<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class CategoryFormat extends Enumeration
{
    const OBJECTS = 0;
    const UIDS = 2;
    const CSV = 3;
}
