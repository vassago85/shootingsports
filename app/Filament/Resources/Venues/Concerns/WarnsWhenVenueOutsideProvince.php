<?php

namespace App\Filament\Resources\Venues\Concerns;

use App\Models\Venue;
use Filament\Notifications\Notification;

/**
 * Soft warning on Venue save when a pinned lat/lng falls outside the
 * selected province's bounding box. Deliberately a notification, not
 * a validation failure — border venues and generous boxes should not
 * block staff from saving a correction.
 */
trait WarnsWhenVenueOutsideProvince
{
    protected function warnIfVenueOutsideProvince(Venue $venue): void
    {
        if ($venue->lat === null || $venue->lng === null || $venue->province === null) {
            return;
        }

        $box = config('province_bounds.'.$venue->province->value);

        if (! is_array($box)) {
            return;
        }

        $lat = (float) $venue->lat;
        $lng = (float) $venue->lng;

        $outside = $lat < $box['min_lat']
            || $lat > $box['max_lat']
            || $lng < $box['min_lng']
            || $lng > $box['max_lng'];

        if (! $outside) {
            return;
        }

        Notification::make()
            ->title('Pin may be outside '.$venue->province->getLabel())
            ->body(sprintf(
                '%s, %s is outside the province box. Double-check the pin or the province before verifying.',
                number_format($lat, 5),
                number_format($lng, 5),
            ))
            ->warning()
            ->persistent()
            ->send();
    }
}
