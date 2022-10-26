<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

trait ExtbaseMailTrait
{
    protected function createMailMessage(
        ?MailService &$mailService,
        ?StandaloneView &$mailView,
        ?MailMessage &$message
    ): void {
        $config = MailConfiguration::fromArray($this->settings['mail']);
        $config->extensionName = $this->request->getControllerExtensionName();
        $config->pluginName = $this->request->getPluginName();
        $config->controllerName = $this->request->getControllerName();

        $mailService = GeneralUtility::makeInstance(MailService::class, $config);
        $mailService->injectEventDispatcher(GeneralUtility::makeInstance(EventDispatcherInterface::class));

        $message = $mailService->createMessage();

        $mailView = $mailService->createMailView(
            $message,
            $this->configurationManager->getContentObject(),
            $this->controllerContext ?? null
        );

        $mailView->assign('settings', $this->settings);
    }
}
