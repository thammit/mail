<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

final class Constants
{
    const MAIL_MODULE_NAME = 'mail';
    const DEFAULT_MAIL_PAGE_TYPE = 24;

    const PANEL_INTERNAL = 'internal';
    const PANEL_EXTERNAL = 'external';
    const PANEL_QUICK_MAIL = 'quickmail';
    const PANEL_DRAFT = 'draft';

    const CONTENT_SECTION_BOUNDARY = 'MAIL_SECTION_BOUNDARY';

    const MAIL_HEADER_IDENTIFIER = 'X-TYPO3MID';
}
