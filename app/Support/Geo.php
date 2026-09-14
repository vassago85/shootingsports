<?php

namespace App\Support;

use App\Enums\Province;

class Geo
{
    /**
     * @return array{0: float, 1: float}
     */
    public static function provinceCentroid(Province $province): array
    {
        return match ($province) {
            Province::EasternCape => [-32.2968, 26.4194],
            Province::FreeState => [-29.0852, 26.1596],
            Province::Gauteng => [-26.2708, 28.1123],
            Province::KwaZuluNatal => [-28.5306, 30.8958],
            Province::Limpopo => [-23.4013, 29.4179],
            Province::Mpumalanga => [-25.5653, 30.5279],
            Province::NorthernCape => [-29.0467, 21.8569],
            Province::NorthWest => [-26.6639, 25.2838],
            Province::WesternCape => [-33.2278, 21.8569],
        };
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
