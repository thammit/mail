<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

final class Constants
{
    const MAIL_MODULE_NAME = 'dmail';
    const MAIL_TYPE_INTERNAL = 0;
    const MAIL_TYPE_EXTERNAL = 1;
    const MAIL_TYPE_DRAFT_INTERNAL = 2;
    const MAIL_TYPE_DRAFT_EXTERNAL = 3;

    const RECIPIENT_GROUP_TYPE_PAGES = 0;
    const RECIPIENT_GROUP_TYPE_CSV = 1;
    const RECIPIENT_GROUP_TYPE_STATIC = 2;
    const RECIPIENT_GROUP_TYPE_QUERY = 3;
    const RECIPIENT_GROUP_TYPE_OTHER = 4;

    const WIZARD_STEP_OVERVIEW = '';
    const WIZARD_STEP_SETTINGS = 'info';
    const WIZARD_STEP_CATEGORIES = 'cats';
    const WIZARD_STEP_SEND_TEST = 'send_test';
    const WIZARD_STEP_SEND_TEST2 = 'send_mail_test';
    const WIZARD_STEP_FINAL = 'send_mail_final';
    const WIZARD_STEP_SEND = 'send_mass';

    const PANEL_INTERNAL = 'int';
    const PANEL_EXTERNAL = 'ext';
    const PANEL_QUICK_MAIL = 'quick';
    const PANEL_OPEN_STORED = 'dmail';
}
