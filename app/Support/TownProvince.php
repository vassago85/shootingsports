<?php

namespace App\Support;

use App\Enums\Province;

/**
 * Local place names whose province is unambiguous.
 *
 * Used by the data-quality check before a live geocode, so a town
 * stored against the wrong province is caught without a network call.
 * Ambiguous names (Middelburg, Richmond) are left out on purpose.
 */
final class TownProvince
{
    /**
     * @var array<string, Province>
     */
    private const TOWNS = [
        'kraaifontein' => Province::WesternCape,
        'cape town' => Province::WesternCape,
        'stellenbosch' => Province::WesternCape,
        'paarl' => Province::WesternCape,
        'george' => Province::WesternCape,
        'worcester' => Province::WesternCape,
        'bellville' => Province::WesternCape,
        'durbanville' => Province::WesternCape,
        'somerset west' => Province::WesternCape,
        'knysna' => Province::WesternCape,
        'pretoria' => Province::Gauteng,
        'johannesburg' => Province::Gauteng,
        'centurion' => Province::Gauteng,
        'sandton' => Province::Gauteng,
        'midrand' => Province::Gauteng,
        'benoni' => Province::Gauteng,
        'soweto' => Province::Gauteng,
        'krugersdorp' => Province::Gauteng,
        'durban' => Province::KwaZuluNatal,
        'pietermaritzburg' => Province::KwaZuluNatal,
        'newcastle' => Province::KwaZuluNatal,
        'richards bay' => Province::KwaZuluNatal,
        'nelspruit' => Province::Mpumalanga,
        'mbombela' => Province::Mpumalanga,
        'witbank' => Province::Mpumalanga,
        'emalahleni' => Province::Mpumalanga,
        'secunda' => Province::Mpumalanga,
        'polokwane' => Province::Limpopo,
        'tzaneen' => Province::Limpopo,
        'bloemfontein' => Province::FreeState,
        'welkom' => Province::FreeState,
        'rustenburg' => Province::NorthWest,
        'potchefstroom' => Province::NorthWest,
        'klerksdorp' => Province::NorthWest,
        'kimberley' => Province::NorthernCape,
        'upington' => Province::NorthernCape,
        'gqeberha' => Province::EasternCape,
        'port elizabeth' => Province::EasternCape,
        'east london' => Province::EasternCape,
        'makhanda' => Province::EasternCape,
        'mthatha' => Province::EasternCape,
    ];

    public static function forTown(?string $town): ?Province
    {
        $key = mb_strtolower(trim((string) $town));

        if ($key === '') {
            return null;
        }

        return self::TOWNS[$key] ?? null;
    }

    public static function fromLabel(?string $label): ?Province
    {
        $needle = mb_strtolower(trim((string) $label));

        if ($needle === '') {
            return null;
        }

        foreach (Province::cases() as $province) {
            if (mb_strtolower($province->getLabel()) === $needle) {
                return $province;
            }
        }

        return null;
    }
}
