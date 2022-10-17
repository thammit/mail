<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class ResponseType extends Enumeration
{
    const PING             = -1;
    const ALL              = 0;
    const HTML             = 1;
    const PLAIN            = 2;
    const FAILED           = -127;
}
