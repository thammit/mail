<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class ReturnCodes extends Enumeration
{
    const RECIPIENT_UNKNOWN   = 550;
    const RECIPIENT_NOT_LOCAL = 551;
    const MAILBOX_FULL        = 552;
    const MAILBOX_INVALID     = 553;
    const TRANSACTION_FAILED  = 554;
    const UNKNOWN_REASON      = -1;
}
