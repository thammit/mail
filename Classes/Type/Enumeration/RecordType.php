<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class RecordType extends Enumeration
{
    const ADDRESS             = 0b00000001;
    const FRONTEND_USER       = 0b00000010;
    const CUSTOM              = 0b00000100;
    const FRONTEND_USER_GROUP = 0b00001000;
}
