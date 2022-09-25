<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class MailType extends Enumeration
{
    const INTERNAL = 0;
    const EXTERNAL = 1;
    const DRAFT_INTERNAL = 2;
    const DRAFT_EXTERNAL = 3;
}
