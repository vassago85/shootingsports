<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Pretoria Precision Rifle Club year-end matches for December 2026.
 *
 * Two matches both run on Saturday 5 December 2026 — a PR22 rimfire
 * club match at Pappaburg and a PRS centerfire year-end at Legends
 * Adventure Farm. Sourced from https://pretoriaprc.co.za (the club's
 * own site) with the Pappaburg province cross-checked against the
 * SAPRF calendar (Gauteng).
 *
 * Idempotent — safe to re-run every deploy. The organisation and
 * venues use `firstOrCreate` so claims, logos, descriptions and other
 * curated fields are left alone once a human has touched them. The
 * events use `updateOrCreate` so date / status corrections flow
 * through on each deploy.
 */
class PprcDecember2026Seeder extends Seeder
{
    public function run(): void
    {
        $prs = Discipline::query()->where('slug', 'prs')->first();
        $pr22 = Discipline::query()->where('slug', 'pr22-rimfire')->first();

        if ($prs === null || $pr22 === null) {
            // Disciplines haven't been seeded yet — nothing to attach.
            return;
        }

        $pprc = Organisation::query()->firstOrCreate(
            ['slug' => 'pretoria-precision-rifle-club'],
            [
                'name' => 'Pretoria Precision Rifle Club',
                'short_name' => 'PPRC',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'website_url' => 'https://pretoriaprc.co.za',
                'email' => 'info@pretoriaprc.co.za',
                'description' => 'Precision rifle club in Pretoria, Gauteng, established in 2023 and run as a SAPRF affiliate. Hosts PRS (centerfire) and PR22 rimfire matches for shooters wanting to grow, belong, and compete in precision rifle.',
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $pprc->syncDisciplines([$prs->id, $pr22->id], $prs->id);

        $pappaburg = Venue::query()->firstOrCreate(
            ['slug' => 'pappaburg'],
            [
                'name' => 'Pappaburg',
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'access' => VenueAccess::GuestByArrangement,
                'notes' => 'PR22 rimfire venue used by PPRC and SAPRF Gauteng Provincial matches.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $pappaburg->syncDisciplines([$pr22->id], $pr22->id);

        $legends = Venue::query()->firstOrCreate(
            ['slug' => 'legends-adventure-farm'],
            [
                'name' => 'Legends Adventure Farm',
                'province' => Province::Gauteng,
                'town' => 'Rayton',
                'metro' => GautengMetro::Pretoria,
                'access' => VenueAccess::GuestByArrangement,
                'notes' => 'PRS centerfire venue used by PPRC year-end and SAPRF Gauteng matches.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $legends->syncDisciplines([$prs->id], $prs->id);

        $rimfire = Event::query()->updateOrCreate(
            ['slug' => 'pprc-rimfire-club-match-year-end-2026-12-05'],
            [
                'title' => 'PPRC Rimfire Club Match year end',
                'host_organisation_id' => $pprc->id,
                'venue_id' => $pappaburg->id,
                'starts_at' => '2026-12-05 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::Confirmed,
                'confirmed_at' => now(),
                'description' => 'Year-end PR22 rimfire club match hosted by PPRC at Pappaburg on Saturday 5 December 2026.',
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $rimfire->syncDisciplines([$pr22->id], $pr22->id);

        $centerfire = Event::query()->updateOrCreate(
            ['slug' => 'pprc-centerfire-year-end-match-2026-12-05'],
            [
                'title' => 'PPRC Center Fire Year End Match',
                'host_organisation_id' => $pprc->id,
                'venue_id' => $legends->id,
                'starts_at' => '2026-12-05 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::Confirmed,
                'confirmed_at' => now(),
                'description' => 'Year-end PRS centerfire match hosted by PPRC at Legends Adventure Farm on Saturday 5 December 2026.',
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $centerfire->syncDisciplines([$prs->id], $prs->id);
    }
}
