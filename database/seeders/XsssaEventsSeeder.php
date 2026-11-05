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

/**
 * XSSSA — Xtreme Steel Shooting South Africa.
 *
 * Taken from the revamped https://www.xsssa.co.za/ (Dr Arnold Rix).
 * Matches are Western Cape only, at Loch Lynne near Prince Alfred
 * Hamlet, 30 teams in 10 groups per match (around 60 shooters), 18
 * targets. Entries run through XSSSA's WhatsApp and Telegram groups
 * and the matches sell out early.
 *
 * The site has 52 matches since 2013 and 532 different shooters but
 * no 2027 dates yet; Arnold normally publishes those closer to the
 * season. The 7 November 2026 match is the one on the calendar now,
 * with entries closed.
 */
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
                'province' => Province::WesternCape,
                'town' => 'Prince Alfred Hamlet',
                'website_url' => 'https://www.xsssa.co.za/',
                'description' => "Xtreme Steel Shooting is a non-standard contest that examines practical rifle skills: target recognition, range estimation, wind doping and marksmanship, in practical challenges over natural terrain using the equipment a competitor can carry.\n\nMatches are Western Cape only, shot at Loch Lynne near Prince Alfred Hamlet. 30 teams in 10 groups per match, around 60 shooters, 18 targets. Entries run through the XSSSA WhatsApp and Telegram groups and sell out early.",
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
                'last_verified_at' => now(),
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
                'description' => 'XSSSA home range in the Koue Bokkeveld near Prince Alfred Hamlet. Natural terrain, 18 steel targets per match, laid out across ten lanes with three teams on each lane.',
                'website_url' => 'https://www.xsssa.co.za/',
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
                'status' => EventStatus::Full,
                'confirmed_at' => now(),
                'entry_url' => 'https://www.xsssa.co.za/calender.html',
                'description' => 'XSSSA match at Loch Lynne, Prince Alfred Hamlet. 30 teams in 10 groups — entries closed and the match is full. Join the XSSSA WhatsApp or Telegram group to hear about the next match.',
                'source' => ListingSource::Import,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }
}
