<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class RecipientGroupType extends Enumeration
{
    const PAGES = 0;
    const CSV = 1;
    const STATIC = 2;
    const QUERY = 3;
    const OTHER = 4;
    const MODEL = 5;
}
