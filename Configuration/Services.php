<?php

declare(strict_types=1);

namespace MEDIAESSENZ\Mail;

use MEDIAESSENZ\Mail\EventListener\AddTtAddressExtTablesSql;
use MEDIAESSENZ\Mail\EventListener\DeactivateAddresses;
use MEDIAESSENZ\Mail\EventListener\ManipulateAddressRecipient;
use MEDIAESSENZ\Mail\Service\ImportService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    // Check if tt_address is present.
    // It has to be done this way (and not using ExtensionManagementUtility::isLoaded('tt_address')), because of a DI issue
    if (class_exists('FriendsOfTYPO3\\TtAddress\\Domain\\Repository\\AddressRepository')) {
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
