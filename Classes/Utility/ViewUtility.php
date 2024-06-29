<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
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
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::NOTICE;
        } else {
            $serverity = ContextualFeedbackSeverity::NOTICE;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, $storeInSession, $identifier);
    }

    public static function addNotificationNotice(string $message, string $title = ''): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::NOTICE;
        } else {
            $serverity = ContextualFeedbackSeverity::NOTICE;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageInfo(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::INFO;
        } else {
            $serverity = ContextualFeedbackSeverity::INFO;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, $storeInSession, $identifier);
    }

    public static function addNotificationInfo(string $message, string $title = ''): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::INFO;
        } else {
            $serverity = ContextualFeedbackSeverity::INFO;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageSuccess(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::OK;
        } else {
            $serverity = ContextualFeedbackSeverity::OK;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, $storeInSession, $identifier);
    }

    public static function addNotificationSuccess(string $message, string $title = ''): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::OK;
        } else {
            $serverity = ContextualFeedbackSeverity::OK;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageWarning(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::WARNING;
        } else {
            $serverity = ContextualFeedbackSeverity::WARNING;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, $storeInSession, $identifier);
    }

    public static function addNotificationWarning(string $message, string $title = ''): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::WARNING;
        } else {
            $serverity = ContextualFeedbackSeverity::WARNING;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, true, self::NOTIFICATIONS);
    }

    public static function addFlashMessageError(string $message, string $title = '', bool $storeInSession = false, string $identifier = 'core.template.flashMessages'): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::ERROR;
        } else {
            $serverity = ContextualFeedbackSeverity::ERROR;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, $storeInSession, $identifier);
    }

    public static function addNotificationError(string $message, string $title = ''): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $serverity = AbstractMessage::ERROR;
        } else {
            $serverity = ContextualFeedbackSeverity::ERROR;
        }
        self::addMessageToFlashMessageQueue($message, $title, $serverity, true, self::NOTIFICATIONS);
    }

    public static function addMessageToFlashMessageQueue(
        string $message,
        string $title,
        int|ContextualFeedbackSeverity $severity,
        bool $storeInSession = false,
        string $identifier = 'core.template.flashMessages',
    ): void {
        self::getFlashMessageQueue($identifier)->addMessage(self::getFlashMessage($message, $title, $severity, $storeInSession));
    }

    public static function getFlashMessage(string $message, string $title, int|ContextualFeedbackSeverity $severity, bool $storeInSession = false): FlashMessage
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
