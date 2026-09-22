<?php

namespace App\Support;

use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Models\Discipline;

/**
 * Assigns each published sport to the firearm division a visitor picks,
 * and creates the sports that the register did not have yet.
 */
final class DivisionCatalog
{
    public static function apply(): void
    {
        self::rename();
        self::createMissing();
        self::assign();
    }

    private static function rename(): void
    {
        foreach ([
            'ipsc-practical' => 'IPSC',
            'steel-challenge' => 'Steel',
            'sporting-clays' => 'English Sporting',
            'compak-five-stand' => 'Compak',
        ] as $slug => $name) {
            Discipline::query()->where('slug', $slug)->update(['name' => $name]);
        }
    }

    private static function createMissing(): void
    {
        $sort = (int) Discipline::query()->max('sort_order');

        foreach (self::missing() as $slug => $sport) {
            Discipline::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $sport['name'],
                    'family' => $sport['family'],
                    'short_blurb' => $sport['blurb'],
                    'is_published' => true,
                    'sort_order' => $sort += 10,
                ],
            );
        }
    }

    private static function assign(): void
    {
        foreach (self::assignments() as $slug => $divisions) {
            $discipline = Discipline::query()->where('slug', $slug)->first();

            if ($discipline === null) {
                continue;
            }

            $discipline->syncDivisions($divisions);
        }
    }

    /**
     * @param  list<Division>  $divisions
     */
    public static function familyFor(array $divisions): DisciplineFamily
    {
        if (count($divisions) > 1) {
            return DisciplineFamily::Multi;
        }

        return $divisions[0]->legacyFamily();
    }

    /**
     * @return array<string, array{name: string, family: DisciplineFamily, blurb: string}>
     */
    private static function missing(): array
    {
        return [
            'idpa' => [
                'name' => 'IDPA',
                'family' => DisciplineFamily::Handgun,
                'blurb' => 'Defensive pistol, shot from concealment, scored on time and procedure.',
            ],
            'training' => [
                'name' => 'Training',
                'family' => DisciplineFamily::Handgun,
                'blurb' => 'Instruction and introductory shoots, kept separate from match series.',
            ],
            'big-bore' => [
                'name' => 'Big Bore',
                'family' => DisciplineFamily::Rifle,
                'blurb' => 'The club big-bore rifle shoots that fill South African ranges.',
            ],
            'ipsc-rifle' => [
                'name' => 'IPSC Rifle',
                'family' => DisciplineFamily::Rifle,
                'blurb' => 'Practical rifle on IPSC stages.',
            ],
            'pcc' => [
                'name' => 'PCC',
                'family' => DisciplineFamily::Rifle,
                'blurb' => 'Pistol-calibre carbine, often shot alongside handgun leagues.',
            ],
            'fitasc' => [
                'name' => 'FITASC',
                'family' => DisciplineFamily::Shotgun,
                'blurb' => 'FITASC sporting and FITASC trap, walked as a parcours.',
            ],
            'down-the-line' => [
                'name' => 'Down the Line',
                'family' => DisciplineFamily::Shotgun,
                'blurb' => 'The club trap line most South African shotgun shooters mean by DTL.',
            ],
        ];
    }

    /**
     * @return array<string, list<Division>>
     */
    public static function assignments(): array
    {
        $handgun = Division::Handgun;
        $bolt = Division::BoltActionRifle;
        $self = Division::SelfLoadingRifle;
        $shotgun = Division::Shotgun;
        $air = Division::AirRifle;
        $shared = [$handgun, $self, $shotgun];

        return [
            'ipsc-practical' => [$handgun],
            'idpa' => [$handgun],
            'steel-challenge' => [$handgun],
            'training' => [$handgun],
            'pin-shooting' => [$handgun],
            'sport-pistol' => [$handgun],
            'target-pistol' => [$handgun],
            'action-defensive' => [$handgun],
            'precision-rifle' => [$bolt],
            'prs' => [$bolt],
            'pr22-rimfire' => [$bolt],
            'elr' => [$bolt],
            'f-class' => [$bolt],
            'target-rifle' => [$bolt],
            'bisley-fullbore' => [$bolt],
            'benchrest' => [$bolt],
            'hunting-rifle' => [$bolt],
            'nrl-hunter' => [$bolt],
            'gong-shooting' => [$bolt],
            'metallic-silhouette' => [$bolt],
            'big-bore' => [$bolt],
            'ipsc-rifle' => [$self],
            'service-rifle' => [$self],
            'combat-rifle' => [$self],
            'pcc' => [$self],
            '3-gun' => $shared,
            'multigun' => $shared,
            'sporting-clays' => [$shotgun],
            'fitasc' => [$shotgun],
            'down-the-line' => [$shotgun],
            'trap' => [$shotgun],
            'skeet' => [$shotgun],
            'compak-five-stand' => [$shotgun],
            '10m-air-rifle' => [$air],
            'field-target' => [$air],
            'hunter-field-target' => [$air],
            'benchrest-airgun' => [$air],
        ];
    }
}
