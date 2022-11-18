<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

final class Constants
{
    const MAIL_MODULE_NAME = 'module_mail';
    const MAIL_MAIL_NAME = 'mail';

    const PANEL_INTERNAL = 'internal';
    const PANEL_EXTERNAL = 'external';
    const PANEL_QUICK_MAIL = 'quickmail';
    const PANEL_OPEN = 'open';

    const CONTENT_SECTION_BOUNDARY = 'DMAILER_SECTION_BOUNDARY';

    const MAIL_HEADER_IDENTIFIER = 'X-TYPO3MID';
}
