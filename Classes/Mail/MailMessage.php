<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Throwable;

class MailMessage extends \TYPO3\CMS\Core\Mail\MailMessage implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected string $siteIdentifier = '';

    protected int $scheduled = 0;

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
     */
    public function send(): bool
    {
        $accepted = false;
        try {
            $this->initializeMailer();
            $this->mailer->send($this);
            $sentMessage = $this->mailer->getSentMessage();
            if ($sentMessage) {
                $accepted = true;
            }
        } catch (Throwable $e) {
            $data['message'] = [
                'to' => $this->getTo() ? $this->getTo()[0]->toString() : '[not-set]',
                'subject' => $this->getSubject()
            ];
            $data['exception'] = $e;
            $this->logger->critical('Email sending caused exception', $data);
        }
        return $accepted;
    }

    public function setSiteIdentifier(string $siteIdentifier): self
    {
        $this->siteIdentifier = $siteIdentifier;
        return $this;
    }
}
