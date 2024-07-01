<?php
namespace MEDIAESSENZ\Mail\DependencyInjection;

use MEDIAESSENZ\Mail\EventListener\DeactivateAddresses;
use MEDIAESSENZ\Mail\EventListener\ManipulateAddressRecipient;
use MEDIAESSENZ\Mail\Service\ImportService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class EventListenerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (ExtensionManagementUtility::isLoaded('tt_address')) {
            $deactivateAddressesDefinition = $container->getDefinition(DeactivateAddresses::class);
            $deactivateAddressesDefinition->addTag('event.listener', [
                'identifier' => 'mediaessenz/mail/deactivate-addresses'
            ]);
            $manipulateAddressDefinition = $container->getDefinition(ManipulateAddressRecipient::class);
            $manipulateAddressDefinition->addTag('event.listener', [
                'identifier' => 'mediaessenz/mail/manipulate-address-recipient',
                'before' => 'mediaessenz/mail/normalize-recipient-data'
            ]);
            $importServiceDefinition = $container->getDefinition(ImportService::class);
            $importServiceDefinition->setPublic(true);
        }
    }
}
