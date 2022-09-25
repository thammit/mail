<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Enumeration;

use TYPO3\CMS\Core\Type\Enumeration;

class Action extends Enumeration
{
    const __default = self::WIZARD_STEP_OVERVIEW;
    const WIZARD_STEP_OVERVIEW = '';
    const WIZARD_STEP_SETTINGS = 'info';
    const WIZARD_STEP_CATEGORIES = 'cats';
    const WIZARD_STEP_SEND_TEST = 'send_test';
    const WIZARD_STEP_SEND_TEST2 = 'send_mail_test';
    const WIZARD_STEP_FINAL = 'send_mail_final';
    const WIZARD_STEP_SEND = 'send_mass';

    const DELETE_MAIL = 'delete';

    const RECIPIENT_LIST_USER_INFO = 'displayUserInfo';
    const RECIPIENT_LIST_MAIL_GROUP = 'displayMailGroup';
    const RECIPIENT_LIST_IMPORT = 'displayImport';

    const STATS = 'stats';
}
