<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ViewUtility
{

    public static function getFlashMessageQueue(string $identifier = 'core.template.flashMessages'): FlashMessageQueue
    {
        return GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier($identifier);
    }

    public static function addWarningToFlashMessageQueue(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::WARNING, $storeInSession, $identifier);
    }

    public static function addInfoToFlashMessageQueue(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::INFO, $storeInSession, $identifier);
    }

    public static function addOkToFlashMessageQueue(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::OK, $storeInSession, $identifier);
    }

    public static function addErrorToFlashMessageQueue(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::ERROR, $storeInSession, $identifier);
    }

    public static function addMessageToFlashMessageQueue(
        string $message,
        string $title,
        int $severity,
        bool $storeInSession = false,
        string $identifier = 'core.template.flashMessages',
    ): void {
        self::getFlashMessageQueue($identifier)->addMessage(self::getFlashMessage($message, $title, $severity, $storeInSession));
    }

    public static function getFlashMessage(string $message, string $title, int $severity, bool $storeInSession = false): FlashMessage
    {
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            $storeInSession
        );
    }
}
