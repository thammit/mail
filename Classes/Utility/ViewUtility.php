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
    const NOTIFICATIONS = 'mail.notifications';

    public static function getFlashMessageQueue(string $identifier = 'core.template.flashMessages'): FlashMessageQueue
    {
        return GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier($identifier);
    }

    public static function addFlashMessageNotice(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::NOTICE, $storeInSession, $identifier);
    }

    public static function addNotificationNotice(string $message, string $title = ''): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::NOTICE, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageInfo(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::INFO, $storeInSession, $identifier);
    }

    public static function addNotificationInfo(string $message, string $title = ''): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::INFO, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageSuccess(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::OK, $storeInSession, $identifier);
    }

    public static function addNotificationSuccess(string $message, string $title = ''): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::OK, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageWarning(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::WARNING, $storeInSession, $identifier);
    }

    public static function addNotificationWarning(string $message, string $title = ''): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::WARNING, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageError(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::ERROR, $storeInSession, $identifier);
    }

    public static function addNotificationError(string $message, string $title = ''): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::ERROR, true, self::NOTIFICATIONS);
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
