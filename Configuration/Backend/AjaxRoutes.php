<?php

use MEDIAESSENZ\Mail\Controller\MailController;

return [
    'mail_save-preview-image' => [
        'path' => '/mail/save-preview-image',
        'target' => MailController::class . '::savePreviewImageAction'
    ],
    'mail_save-category-restrictions' => [
        'path' => '/mail/save-category-restrictions',
        'target' => MailController::class . '::updateCategoryRestrictionsAction'
    ],
];
