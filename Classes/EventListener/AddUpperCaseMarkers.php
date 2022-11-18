<?php

namespace MEDIAESSENZ\Mail\EventListener;

use MEDIAESSENZ\Mail\Events\ManipulateMarkersEvent;

class AddUpperCaseMarkers
{
    public function __invoke(ManipulateMarkersEvent $event): void
    {
        $markers = $event->getMarkers();
        foreach ($markers as $marker => $value) {
            $uppercaseMarker = strtoupper($marker);
            if (str_starts_with($marker, '###USER_') && !array_key_exists($uppercaseMarker, $markers)) {
                $markers[$uppercaseMarker] = strtoupper($value);
            }
        }
        $event->setMarkers($markers);
    }
}
