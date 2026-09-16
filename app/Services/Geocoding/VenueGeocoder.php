<?php

namespace App\Services\Geocoding;

use App\Enums\GeocodeSource;
use App\Models\Venue;

/**
 * Applies geocoding to Venue rows with staff-pin protection.
 */
class VenueGeocoder
{
    public function __construct(private Geocoder $geocoder) {}

    /**
     * Geocode a venue when it has no pin, or when $force is true and
     * the pin is not staff-locked.
     */
    public function fill(Venue $venue, bool $force = false): bool
    {
        if ($venue->geocode_source === GeocodeSource::Staff && ! $force) {
            return false;
        }

        if (! $force && $venue->lat !== null && $venue->lng !== null) {
            return false;
        }

        // Force still refuses to overwrite a staff pin unless the
        // caller explicitly clears geocode_source first.
        if ($force && $venue->geocode_source === GeocodeSource::Staff) {
            return false;
        }

        $result = null;

        foreach ($this->queriesFor($venue) as $i => $query) {
            if ($i > 0 && ! app()->runningUnitTests()) {
                // Nominatim usage policy: ~1 request/second.
                usleep(1_100_000);
            }

            $result = $this->geocoder->geocode($query);

            if ($result) {
                break;
            }
        }

        if (! $result) {
            return false;
        }

        $venue->fill([
            'lat' => $result->lat,
            'lng' => $result->lng,
            'geocode_source' => GeocodeSource::Nominatim,
            'geocoded_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Cascade from specific (name/address) to town+province so obscure
     * ranges still land near the right town when OSM has no club pin.
     *
     * @return list<string>
     */
    public function queriesFor(Venue $venue): array
    {
        $province = $venue->province?->getLabel();
        $candidates = [];

        $push = function (array $parts) use (&$candidates): void {
            $query = collect($parts)
                ->filter(fn ($part): bool => filled($part))
                ->unique()
                ->implode(', ');

            if ($query !== '' && ! in_array($query, $candidates, true)) {
                $candidates[] = $query;
            }
        };

        $push([$venue->name, $venue->town, $province, 'South Africa']);
        $push([$venue->address, $venue->town, $province, 'South Africa']);
        $push([$venue->town, $province, 'South Africa']);

        return $candidates;
    }

    public function queryFor(Venue $venue): string
    {
        return $this->queriesFor($venue)[0] ?? 'South Africa';
    }

    /**
     * Geocode a free-text "near" town for the distance filter.
     */
    public function geocodePlace(string $place): ?GeocodeResult
    {
        $place = trim($place);

        if ($place === '') {
            return null;
        }

        if (! str_contains(strtolower($place), 'south africa')) {
            $place .= ', South Africa';
        }

        return $this->geocoder->geocode($place);
    }
}
