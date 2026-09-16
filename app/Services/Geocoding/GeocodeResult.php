<?php

namespace App\Services\Geocoding;

readonly class GeocodeResult
{
    public function __construct(
        public float $lat,
        public float $lng,
        public ?string $displayName = null,
    ) {}
}
