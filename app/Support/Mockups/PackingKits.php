<?php

namespace App\Support\Mockups;

use Illuminate\Support\Str;

/**
 * Starter packing lists for the app mockup. The register does not store
 * kit items, so these lists are mock copy keyed to real sport slugs.
 */
class PackingKits
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function basics(?string $slug, ?string $family = null): array
    {
        $slug = $slug ?? '';

        if ($slug !== '' && isset(self::BY_SLUG[$slug])) {
            return self::rows(self::BY_SLUG[$slug]);
        }

        $family = $family ?? '';

        if ($family !== '' && isset(self::BY_FAMILY[$family])) {
            return self::rows(self::BY_FAMILY[$family]);
        }

        if ($slug === '' && $family === '') {
            return [];
        }

        return self::rows([
            'Ammunition for the course',
            'Eye protection',
            'Ear protection',
            'Chamber flag',
        ]);
    }

    /**
     * @param  list<string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private static function rows(array $labels): array
    {
        $items = [];
        $used = [];

        foreach ($labels as $label) {
            $key = Str::slug($label);

            if ($key === '' || isset($used[$key])) {
                continue;
            }

            $used[$key] = true;
            $items[] = ['key' => $key, 'label' => $label];
        }

        return $items;
    }

    /**
     * @var array<string, list<string>>
     */
    private const BY_SLUG = [
        'precision-rifle' => [
            'Rifle',
            'Match ammunition, plus a few spare rounds',
            'Magazine',
            'Bipod',
            'Rear bag',
            'Ballistic app or data card',
            'Rangefinder',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'prs' => [
            'Rifle',
            'Match ammunition, plus a few spare rounds',
            'Magazine',
            'Bipod',
            'Rear bag',
            'Ballistic app or data card',
            'Rangefinder',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'nrl-hunter' => [
            'Rifle',
            'Match ammunition, plus a few spare rounds',
            'Magazine',
            'Bipod',
            'Rear bag',
            'Ballistic app or data card',
            'Rangefinder',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'pr22-rimfire' => [
            'Rimfire rifle',
            '.22 ammunition',
            'Magazine',
            'Bipod and rear bag',
            'Data card',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'elr' => [
            'Rifle',
            'Counted match ammunition',
            'Bipod and rear rest',
            'Ballistic solver with a known velocity',
            'Spotting scope',
            'Rangefinder',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'f-class' => [
            'Rifle',
            'Ammunition',
            'Rest or sling the rules allow',
            'Rear bag',
            'Spotting scope',
            'Score book',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'target-rifle' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Spotting scope',
            'Score book',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'bisley-fullbore' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Spotting scope',
            'Score book',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'benchrest' => [
            'Rifle',
            'Ammunition',
            'Front rest and rear bag',
            'Spotting scope',
            'Score book',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'service-rifle' => [
            'Rifle',
            'Magazines',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'combat-rifle' => [
            'Rifle',
            'Magazines',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'hunting-rifle' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'gong-shooting' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'metallic-silhouette' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'big-bore' => [
            'Rifle',
            'Ammunition',
            'Sling',
            'Spotting scope',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'ipsc-rifle' => [
            'Rifle',
            'Magazines',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
            'Closed shoes',
        ],
        'pcc' => [
            'Pistol-calibre carbine',
            'Magazines',
            'Ammunition',
            'Sling',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'ipsc-practical' => [
            'Pistol',
            'Holster and belt',
            'Magazines',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
            'Closed shoes',
        ],
        'steel-challenge' => [
            'Pistol',
            'Holster',
            'Five magazines',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'sport-pistol' => [
            'Pistol',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'target-pistol' => [
            'Pistol',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'pin-shooting' => [
            'Pistol',
            'Holster and belt',
            'Magazines',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'action-defensive' => [
            'Pistol',
            'Holster and belt',
            'Magazines',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
            'Closed shoes',
        ],
        'idpa' => [
            'Pistol',
            'Holster and belt',
            'Magazines',
            'Ammunition',
            'Cover garment, if the division asks for one',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'training' => [
            'Pistol',
            'Holster and belt',
            'Magazines',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
            'Notebook',
        ],
        'trap' => [
            'Shotgun',
            'Cartridges for the round',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
            'Choke key, if you change chokes',
        ],
        'skeet' => [
            'Shotgun',
            'Cartridges for the round',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
            'Choke key, if you change chokes',
        ],
        'sporting-clays' => [
            'Shotgun',
            'Cartridges for the course',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
            'Choke key',
        ],
        'compak-five-stand' => [
            'Shotgun',
            'Cartridges for the round',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
        ],
        'wingshooting' => [
            'Shotgun',
            'Cartridges',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
        ],
        'fitasc' => [
            'Shotgun',
            'Cartridges for the course',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
            'Choke key',
        ],
        'down-the-line' => [
            'Shotgun',
            'Cartridges for the round',
            'Eye and ear protection',
            'Hat',
            'Shell pouch',
        ],
        '10m-air-rifle' => [
            'Air rifle',
            'Pellets',
            'Shooting jacket, if the rules use one',
            'Eye protection',
        ],
        'field-target' => [
            'Air rifle',
            'Pellets',
            'Air bottle or pump, if it is a PCP',
            'Eye protection',
        ],
        'hunter-field-target' => [
            'Air rifle',
            'Pellets',
            'Air bottle or pump, if it is a PCP',
            'Sling or rest the rules allow',
            'Eye protection',
        ],
        'benchrest-airgun' => [
            'Air rifle',
            'Pellets',
            'Air bottle or pump, if it is a PCP',
            'Front rest and rear bag',
            'Eye protection',
        ],
        '3-gun' => [
            'Rifle, pistol and shotgun',
            'Ammunition for each',
            'Magazines',
            'Holster and belt',
            'Eye and ear protection',
            'Chamber flags',
        ],
        'multigun' => [
            'Rifle, pistol and shotgun',
            'Ammunition for each',
            'Magazines',
            'Holster and belt',
            'Eye and ear protection',
            'Chamber flags',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const BY_FAMILY = [
        'rifle' => [
            'Rifle',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'handgun' => [
            'Pistol',
            'Ammunition',
            'Eye and ear protection',
            'Chamber flag',
        ],
        'shotgun' => [
            'Shotgun',
            'Cartridges',
            'Eye and ear protection',
        ],
        'airgun' => [
            'Air rifle',
            'Pellets',
            'Eye protection',
        ],
        'multi' => [
            'Rifle, pistol and shotgun',
            'Ammunition for each',
            'Magazines',
            'Eye and ear protection',
            'Chamber flags',
        ],
    ];
}
