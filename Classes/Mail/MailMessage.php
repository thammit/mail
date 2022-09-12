<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailMessage extends \TYPO3\CMS\Core\Mail\MailMessage
{
    protected string $siteIdentifier = '';

    /**
     * @throws Exception
     */
    private function initializeMailer(): void
    {
        $this->mailer = GeneralUtility::makeInstance(Mailer::class);
        $this->mailer->init($this->siteIdentifier);
    }

    /**
     * Sends the message.
     *
     * This is a shorthand method. It is however more useful to create
     * a Mailer instance which can be used via Mailer->send($message);
     *
     * @return bool whether the message was accepted or not
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function send(): bool
    {
        $this->initializeMailer();
        $this->sent = false;
        $this->mailer->send($this);
        $sentMessage = $this->mailer->getSentMessage();
        if ($sentMessage) {
            $this->sent = true;
        }
        return $this->sent;
    }

    public function setSiteIdentifier(string $siteIdentifier): self
    {
        $this->siteIdentifier = $siteIdentifier;
        return $this;
    }
}
