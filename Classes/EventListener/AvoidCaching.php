<?php
namespace MEDIAESSENZ\Mail\EventListener;

use TYPO3\CMS\Frontend\Event\ShouldUseCachedPageDataIfAvailableEvent;

class AvoidCaching
{
    public function __invoke(ShouldUseCachedPageDataIfAvailableEvent $event)
    {
        // because of unknown reasons TYPO3 13.2 doesn't load the ext_typoscript_setup.typoscript
        // in some cases. To be sure this happens this hack is nessessary.
        if (($event->getRequest()->getQueryParams()['mail'] ?? false) && ($event->getRequest()->getQueryParams()['jumpurl'] ?? false)) {
            $event->setShouldUseCachedPageData(false);
        }
    }
}
