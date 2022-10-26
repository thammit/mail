<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Copyright (c) Reelworx GmbH
 */

namespace MEDIAESSENZ\Mail\Events;

use MEDIAESSENZ\Mail\Mail\MailContent;

final class MailRenderedEvent
{
    protected MailContent $mailContent;

    public function __construct(MailContent $mailContent)
    {
        $this->mailContent = $mailContent;
    }

    public function getMailContent(): MailContent
    {
        return $this->mailContent;
    }
}
