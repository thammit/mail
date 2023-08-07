<?php

use MEDIAESSENZ\Mail\Controller\MailController;

return [
    'mail_save_preview_image' => [
        'path' => '/mail/save-preview-image',
        'methods' => ['POST'],
        'target' => MailController::class . '::savePreviewImageAction'
    ],
    'mail_save_category_restrictions' => [
        'path' => '/mail/save-category-restrictions',
        'methods' => ['POST'],
        'target' => MailController::class . '::updateCategoryRestrictionsAction'
    ],
    'mail_queue_state' => [
        'path' => '/mail/queue-state',
        'methods' => ['GET'],
        'target' => \MEDIAESSENZ\Mail\Controller\QueueController::class . '::state'
    ],
];
