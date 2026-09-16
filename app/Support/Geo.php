<?php

namespace App\Support;

use App\Enums\Province;

class Geo
{
    /**
     * Rough SA mainland bounding box used to catch swapped / defaulted
     * coordinates before they plot in the Indian Ocean.
     */
    public const SA_LAT_MIN = -35.5;

    public const SA_LAT_MAX = -22.0;

    public const SA_LNG_MIN = 16.0;

    public const SA_LNG_MAX = 33.5;

    /**
     * @return array{0: float, 1: float}
     */
    public static function provinceCentroid(Province $province): array
    {
        $point = match ($province) {
            Province::EasternCape => [-32.2968, 26.4194],
            Province::FreeState => [-29.0852, 26.1596],
            Province::Gauteng => [-26.2708, 28.1123],
            Province::KwaZuluNatal => [-28.5306, 30.8958],
            Province::Limpopo => [-23.4013, 29.4179],
            Province::Mpumalanga => [-25.5653, 30.5279],
            Province::NorthernCape => [-29.0467, 21.8569],
            Province::NorthWest => [-26.6639, 25.2838],
            // Was incorrectly sharing Northern Cape's lng (21.86) —
            // inland WC centroid sits nearer Beaufort West.
            Province::WesternCape => [-33.2278, 20.4500],
        };

        return self::clampToSouthAfrica($point[0], $point[1]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    public static function clampToSouthAfrica(float $lat, float $lng): array
    {
        return [
            max(self::SA_LAT_MIN, min(self::SA_LAT_MAX, $lat)),
            max(self::SA_LNG_MIN, min(self::SA_LNG_MAX, $lng)),
        ];
    }

    public static function isInsideSouthAfrica(float $lat, float $lng): bool
    {
        return $lat >= self::SA_LAT_MIN
            && $lat <= self::SA_LAT_MAX
            && $lng >= self::SA_LNG_MIN
            && $lng <= self::SA_LNG_MAX;
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
