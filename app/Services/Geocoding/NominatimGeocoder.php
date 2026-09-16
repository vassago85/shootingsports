<?php

namespace App\Services\Geocoding;

use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenStreetMap Nominatim geocoder. Free, no API key. Strict usage:
 * identify ourselves, cache results, and stay under ~1 req/sec.
 *
 * Google Geocoding can replace this behind the same Geocoder interface
 * later without changing callers.
 */
class NominatimGeocoder implements Geocoder
{
    public function geocode(string $query): ?GeocodeResult
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        try {
            $payload = Http::timeout(12)
                ->withOptions(['verify' => $this->certificateAuthority()])
                ->withHeaders([
                    'User-Agent' => 'ShootingSportsBot/1.0 (+https://shootingsports.co.za; geocoding venues)',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'za',
                    'addressdetails' => 0,
                ])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::warning('Nominatim geocode failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $hit = $payload[0] ?? null;

        if (! is_array($hit)) {
            return null;
        }

        $lat = isset($hit['lat']) ? (float) $hit['lat'] : null;
        $lng = isset($hit['lon']) ? (float) $hit['lon'] : null;

        if ($lat === null || $lng === null || ! Geo::isInsideSouthAfrica($lat, $lng)) {
            return null;
        }

        return new GeocodeResult(
            lat: $lat,
            lng: $lng,
            displayName: isset($hit['display_name']) ? (string) $hit['display_name'] : null,
        );
    }

    /**
     * Prefer PHP's curl.cainfo; on Laragon Windows fall back to the
     * bundled cacert so Nominatim HTTPS works without php.ini edits.
     */
    private function certificateAuthority(): bool|string
    {
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        $laragon = 'C:\\laragon\\etc\\ssl\\cacert.pem';

        if (is_file($laragon)) {
            return $laragon;
        }

        return true;
    }
}
