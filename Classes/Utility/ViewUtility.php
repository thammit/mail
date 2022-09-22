<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Utility;

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
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

    public static function addWarningToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::WARNING, $identifier);
    }

    public static function addInfoToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::INFO, $identifier);
    }

    public static function addOkToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::OK, $identifier);
    }

    public static function addErrorToFlashMessageQueue(string $message, string $title = '', string $identifier = 'core.template.flashMessages'): void
    {
        self::addMessageToFlashMessageQueue($message, $title, AbstractMessage::ERROR, $identifier);
    }

    public static function addMessageToFlashMessageQueue(
        string $message,
        string $title,
        int $severity,
        string $identifier = 'core.template.flashMessages'
    ): void {
        self::getFlashMessageQueue($identifier)->addMessage(self::getFlashMessage($message, $title, $severity));
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

    /**
     * generate edit link for records
     *
     * @param $params
     * @return string
     * @throws RouteNotFoundException
     */
    public static function getEditOnClickLink($params): string
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return 'window.location.href=' . GeneralUtility::quoteJSvalue((string)$uriBuilder->buildUriFromRoute('record_edit', $params)) . '; return false;';
    }
}
