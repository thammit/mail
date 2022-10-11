<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class Gender extends Enumeration
{
    const UNKNOWN = '';
    const MALE = 'm';
    const FEMALE = 'f';
    const VARIOUS = 'v';
}
