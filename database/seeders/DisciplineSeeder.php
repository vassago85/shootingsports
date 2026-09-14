<?php

namespace Database\Seeders;

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
use Illuminate\Database\Seeder;

class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        $precisionRifle = $this->upsert([
            'slug' => 'precision-rifle',
            'name' => 'Precision Rifle',
            'family' => DisciplineFamily::Rifle,
            'short_blurb' => 'Field shooting against steel at unknown and known distances, off barricades, tank traps and natural terrain, under a time limit.',
            'body' => "A match is a series of stages, each under a time limit of roughly 90 to 120 seconds, in which you engage steel targets from an improvised position — off a barricade, a roof prop, a tyre, a rock, or your pack on uneven ground. Distances are called or ranged, and often unknown until you walk up to the stage.\n\nThe rifle is rarely the limiting factor. Reading wind, building a stable position quickly and getting your data right matter more than the last quarter-MOA of mechanical accuracy. Matches are squadded and shot in rotation, with your squad spotting and scoring for each other — which is why it is unusually easy to walk into as a newcomer.\n\nCenterfire is open on chambering: anything up to .308 calibre, under 3 200 fps. In practice that covers 22 Creedmoor, .243, 6 Dasher, 6mm and 6.5 Creedmoor and plenty else — the limits exist to protect the steel, not to prescribe a cartridge.\n\nRimfire is the same game at a tenth of the ammunition cost, and the wind reading is harder rather than easier, which is why most centerfire shooters practise there.",
            'typical_distances' => '50–1 200 m',
            'is_published' => true,
            'sort_order' => $sort += 10,
        ]);

        $this->upsert([
            'slug' => 'prs',
            'name' => 'PRS',
            'family' => DisciplineFamily::Rifle,
            'parent_id' => $precisionRifle->id,
            'short_blurb' => 'Centerfire precision rifle on unknown-distance steel.',
            'typical_distances' => '300–1 000 m',
            'is_published' => false,
            'sort_order' => $sort += 10,
        ]);

        $this->upsert([
            'slug' => 'pr22-rimfire',
            'name' => 'PR22 Rimfire',
            'family' => DisciplineFamily::Rifle,
            'parent_id' => $precisionRifle->id,
            'short_blurb' => 'Rimfire precision rifle — the same game at a tenth of the ammunition cost.',
            'typical_distances' => '50–300 m',
            'is_published' => true,
            'sort_order' => $sort += 10,
        ]);

        foreach ([
            ['f-class', 'F-Class', 'Supported-rifle target shooting at known distance.', '300–1 000 yd'],
            ['target-rifle', 'Target Rifle', 'Sling-supported target rifle on paper.', '300–1 000 yd'],
            ['bisley-fullbore', 'Bisley & Fullbore', 'Classic fullbore target rifle in the Bisley tradition.', '300–900 m'],
            ['benchrest', 'Benchrest', 'Precision from the bench, group or score.', '100–300 m'],
            ['service-rifle', 'Service Rifle', 'Issue-pattern or service-style rifle courses.', '100–600 m'],
            ['combat-rifle', 'Combat Rifle', 'Practical rifle on timed stages.', '25–300 m'],
            ['hunting-rifle', 'Hunting Rifle', 'Field-position hunting rifle matches.', '100–400 m'],
            ['metallic-silhouette', 'Metallic Silhouette', 'Knock-down animal silhouettes at known distance.', '200–500 m'],
        ] as [$slug, $name, $blurb, $distance]) {
            $this->upsert([
                'slug' => $slug,
                'name' => $name,
                'family' => DisciplineFamily::Rifle,
                'short_blurb' => $blurb,
                'typical_distances' => $distance,
                'is_published' => true,
                'sort_order' => $sort += 10,
            ]);
        }

        foreach ([
            ['ipsc-practical', 'IPSC Practical', 'Practical pistol on stages against the clock.', null],
            ['steel-challenge', 'Steel Challenge', 'Timed steel plates from a box.', null],
            ['sport-pistol', 'Sport Pistol', 'Precision sport pistol on paper.', '25 m'],
            ['target-pistol', 'Target Pistol', 'Bullseye and ISSF-style target pistol.', '25–50 m'],
            ['pin-shooting', 'Pin Shooting', 'Bowling pins off a table against the clock.', null],
            ['action-defensive', 'Action / Defensive', 'Defensive pistol courses of fire.', null],
        ] as [$slug, $name, $blurb, $distance]) {
            $this->upsert([
                'slug' => $slug,
                'name' => $name,
                'family' => DisciplineFamily::Handgun,
                'short_blurb' => $blurb,
                'typical_distances' => $distance,
                'is_published' => true,
                'sort_order' => $sort += 10,
            ]);
        }

        foreach ([
            ['trap', 'Trap', 'Rising clay targets going away from the line.', null],
            ['skeet', 'Skeet', 'Crossing clays from high and low houses.', null],
            ['sporting-clays', 'Sporting Clays', 'Simulated hunting birds on a course of stands.', null],
            ['compak-five-stand', 'Compak / Five-Stand', 'Compact sporting from a line of stands.', null],
            ['wingshooting', 'Wingshooting', 'Live-bird and field shotgun sport.', null],
        ] as [$slug, $name, $blurb, $distance]) {
            $this->upsert([
                'slug' => $slug,
                'name' => $name,
                'family' => DisciplineFamily::Shotgun,
                'short_blurb' => $blurb,
                'typical_distances' => $distance,
                'is_published' => true,
                'sort_order' => $sort += 10,
            ]);
        }

        foreach ([
            ['10m-air-rifle', '10m Air Rifle', 'ISSF 10-metre air rifle.', '10 m'],
            ['field-target', 'Field Target', 'Outdoor air rifle on knockdown targets.', '8–50 m'],
            ['hunter-field-target', 'Hunter Field Target', 'Hunting-position air rifle field course.', '8–45 m'],
            ['benchrest-airgun', 'Benchrest Airgun', 'Air rifle from the bench.', '25–50 m'],
        ] as [$slug, $name, $blurb, $distance]) {
            $this->upsert([
                'slug' => $slug,
                'name' => $name,
                'family' => DisciplineFamily::Airgun,
                'short_blurb' => $blurb,
                'typical_distances' => $distance,
                'is_published' => true,
                'sort_order' => $sort += 10,
            ]);
        }

        foreach ([
            ['3-gun', '3-Gun', 'Rifle, pistol and shotgun on the same course.', null],
            ['multigun', 'Multigun', 'Mixed-firearm practical stages.', null],
        ] as [$slug, $name, $blurb, $distance]) {
            $this->upsert([
                'slug' => $slug,
                'name' => $name,
                'family' => DisciplineFamily::Multi,
                'short_blurb' => $blurb,
                'typical_distances' => $distance,
                'is_published' => true,
                'sort_order' => $sort += 10,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(array $attributes): Discipline
    {
        return Discipline::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes,
        );
    }
}
