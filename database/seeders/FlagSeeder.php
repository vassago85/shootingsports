<?php

namespace Database\Seeders;

use App\Enums\FlagFamily;
use App\Models\Flag;
use Illuminate\Database\Seeder;

class FlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['new-shooter-friendly', 'New shooter friendly', FlagFamily::Access, 'The match is set up so a first-timer can shoot without being a burden on the squad.', 10],
            ['coaching-on-the-day', 'Coaching on the day', FlagFamily::Access, 'A coach or experienced shooter is available on the line for newcomers.', 20],
            ['come-and-spectate-first', 'Come and spectate first', FlagFamily::Access, 'Visitors are welcome to watch before they commit to shooting.', 30],
            ['spectators-welcome', 'Spectators welcome', FlagFamily::Access, 'Non-shooting visitors may attend.', 40],
            ['juniors-welcome', 'Juniors welcome', FlagFamily::Access, 'A junior category or supervised junior entries are available.', 50],
            ['ladies-category', 'Ladies category', FlagFamily::Access, 'A ladies / women category is scored separately.', 60],
            ['overnight-camping', 'Overnight camping', FlagFamily::Logistics, 'Camping is available at or adjacent to the venue.', 70],
            ['sanctioned-ranked', 'Sanctioned / ranked', FlagFamily::Competition, 'The match counts toward a federation ranking or league.', 80],
            ['squadding-open', 'Squadding open', FlagFamily::Competition, 'Squad allocation is still open.', 90],
            ['counts-toward-activity-requirements', 'Counts toward activity requirements', FlagFamily::Competition, 'Attendance can be used toward dedicated-activity or similar requirements.', 100],
        ];

        foreach ($flags as [$slug, $name, $family, $definition, $sort]) {
            Flag::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'family' => $family,
                    'definition' => $definition,
                    'sort_order' => $sort,
                    'is_filterable' => true,
                ],
            );
        }
    }
}
