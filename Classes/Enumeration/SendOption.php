<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class SendOption extends Enumeration
{
    const NONE = 0;
    const PLAIN_TEXT_ONLY = 1;
    const HTML_ONLY = 2;
    const BOTH = 3;
}
