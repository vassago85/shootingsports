<?php

namespace App\Support;

/**
 * Parse a pasted "lat, lng" string (Google Maps style) into floats.
 */
class CoordinatePaste
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function parse(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Strip degree symbols and N/S/E/W letters; keep digits, signs, commas, dots, spaces.
        $cleaned = preg_replace('/[°º]/u', '', $raw) ?? $raw;
        $cleaned = preg_replace('/[NnSsEeWw]/', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        if (! preg_match('/^(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)$/', $cleaned, $m)) {
            return null;
        }

        $lat = (float) $m[1];
        $lng = (float) $m[2];

        if (! Geo::isInsideSouthAfrica($lat, $lng)) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
