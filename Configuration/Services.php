<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use MEDIAESSENZ\Mail\EventListener\AddTtAddressExtTablesSql;
use MEDIAESSENZ\Mail\EventListener\DeactivateAddresses;
use MEDIAESSENZ\Mail\EventListener\ManipulateAddressRecipient;
use MEDIAESSENZ\Mail\Service\ImportService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    if (ExtensionManagementUtility::isLoaded('tt_address')) {
        $containerBuilder->registerForAutoconfiguration(AddTtAddressExtTablesSql::class)
            ->addTag('event.listener', [
                'identifier' => 'mediaessenz/mail/add-tt-address-ext-tables-sql',
            ]);
        $containerBuilder->registerForAutoconfiguration(DeactivateAddresses::class)
            ->addTag('event.listener', [
                'identifier' => 'mediaessenz/mail/deactivate-addresses',
            ]);
        $containerBuilder->registerForAutoconfiguration(ManipulateAddressRecipient::class)
            ->addTag('event.listener', [
                'identifier' => 'mediaessenz/mail/manipulate-address-recipient',
                'before' => 'mediaessenz/mail/normalize-recipient-data',
            ]);
        $containerBuilder->registerForAutoconfiguration(ImportService::class)
            ->setPublic(true);
    }
};
