<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class MailStatus extends Enumeration
{
    const DRAFT = 0;
    const SCHEDULED = 1;
    const SENDING = 2;
    const PAUSED = 3;
    const ABORTED = 4;
    const SENT = 5;
}
