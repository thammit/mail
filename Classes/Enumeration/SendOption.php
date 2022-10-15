<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class SendOption extends Enumeration
{
    const NONE              = 0b00000000;
    const PLAIN_TEXT_ONLY   = 0b00000001;
    const HTML_ONLY         = 0b00000010;
    const BOTH              = 0b00000011;
}
