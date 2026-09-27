<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class XsssaEventsSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'xtreme-steel')->firstOrFail();

        $federation = Organisation::query()->updateOrCreate(
            ['slug' => 'xsssa'],
            [
                'name' => 'Xtreme Steel Shooting South Africa',
                'short_name' => 'XSSSA',
                'type' => OrganisationType::Federation,
                'province' => null,
                'town' => null,
                'website_url' => 'https://www.xsssa.co.za/',
                'description' => 'Practical rifle on natural terrain: target recognition, range estimation, wind, and the gear a shooter can carry.',
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        $federation->syncDisciplines([$discipline->id], $discipline->id);
        $discipline->forceFill(['federation_organisation_id' => $federation->id])->save();

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'loch-lynne'],
            [
                'name' => 'Loch Lynne',
                'province' => Province::WesternCape,
                'town' => 'Prince Alfred Hamlet',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        $venue->syncDisciplines([$discipline->id], $discipline->id);

        $event = Event::query()->updateOrCreate(
            ['slug' => 'xtreme-steel-loch-lynne-2026-11-07'],
            [
                'title' => 'Xtreme Steel',
                'host_organisation_id' => $federation->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-11-07 00:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Series,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_url' => 'https://www.xsssa.co.za/calender/calender.html',
                'description' => 'XSSSA match at Loch Lynne, Prince Alfred Hamlet. The XSSSA calendar lists this date with entries open.',
                'source' => ListingSource::Import,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }
}
