<?php

namespace App\Services\Geocoding;

interface Geocoder
{
    /**
     * Resolve a free-text place query to coordinates, or null on miss.
     */
    public function geocode(string $query): ?GeocodeResult;
}
