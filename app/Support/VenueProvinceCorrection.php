<?php

namespace App\Support;

use App\Enums\GeocodeSource;
use App\Enums\Province;
use App\Models\Venue;

/**
 * Rewrites a venue province when the town, address, or name contains
 * one unambiguous place. Staff-set coordinates are left in place.
 */
final class VenueProvinceCorrection
{
    public static function apply(): int
    {
        $updated = 0;

        Venue::query()->orderBy('id')->each(function (Venue $venue) use (&$updated): void {
            $known = self::knownProvince($venue);

            if (! $known instanceof Province || $known === $venue->province) {
                return;
            }

            $venue->province = $known;

            if ($venue->lat !== null && $venue->lng !== null && $venue->geocode_source !== GeocodeSource::Staff) {
                $nearest = Geo::nearestProvince((float) $venue->lat, (float) $venue->lng);

                if ($nearest !== $known) {
                    $venue->lat = null;
                    $venue->lng = null;
                    $venue->geocode_source = null;
                    $venue->geocoded_at = null;
                }
            }

            $venue->save();
            $updated++;
        });

        return $updated;
    }

    public static function knownProvince(Venue $venue): ?Province
    {
        return TownProvince::fromText($venue->town)
            ?? TownProvince::fromText($venue->address)
            ?? TownProvince::fromText($venue->name);
    }
}
