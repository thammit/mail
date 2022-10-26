<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

use TYPO3\CMS\Core\Utility\GeneralUtility;

final class MailConfiguration
{
    public string $senderEmail = '';
    public string $senderName = '';
    public string $cc = '';
    public string $bcc = '';
    public string $replyTo = '';
    public string $organization = '';

    public string $extensionName = '';
    public string $controllerName = '';
    public string $pluginName = '';

    public array $templatePaths = [];
    public array $layoutPaths = [];
    public array $partialPaths = [];

    public array $allowedLanguages = [];

    public bool $setAutoSubmittedHeader = true;

    public static function fromArray(array $settings): self
    {
        $config = new self();
        $config->senderName = $settings['sender_name'] ?? '';
        $config->senderEmail = $settings['sender_email'] ?? '';
        $config->bcc = $settings['recipient_copy'] ?? '';
        $config->replyTo = $settings['replyTo'] ?? '';
        $config->organization = $settings['organization'] ?? '';

        $config->templatePaths = $settings['view']['templateRootPaths'] ?? [];
        $config->layoutPaths = $settings['view']['layoutRootPaths'] ?? [];
        $config->partialPaths = $settings['view']['partialRootPaths'] ?? [];

        $config->allowedLanguages = array_filter(GeneralUtility::trimExplode(',', $settings['languages'] ?? ''));

        return $config;
    }

    /**
     * Check whether the provided language is allowed and return it.
     * Otherwise, use first allowed language.
     *
     * @param string $iso2
     * @return string
     */
    public function validatedLanguage(string $iso2): string
    {
        if ($this->allowedLanguages && !in_array($iso2, $this->allowedLanguages, true)) {
            $iso2 = reset($this->allowedLanguages);
        }
        return $iso2;
    }
}
