<?php
namespace MEDIAESSENZ\Mail\EventListener;

use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;

class AddTtAddressExtTablesSql
{

    public function __construct(protected PackageManager $packageManager)
    {
    }

    /**
     * @throws UnknownPackageException
     */
    public function __invoke(AlterTableDefinitionStatementsEvent $event): void
    {
        if ($this->packageManager->isPackageActive('tt_address')) {
            $package = $this->packageManager->getPackage('mail');
            $packagePath = $package->getPackagePath();
            $event->addSqlData((string)file_get_contents($packagePath . 'tt_address_ext_tables.sql'));
        }
    }
}
