<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class SendFormat extends Enumeration
{
    const NONE  = 0b00000000;
    const PLAIN = 0b00000001;
    const HTML  = 0b00000010;
    const BOTH  = 0b00000011;
}
