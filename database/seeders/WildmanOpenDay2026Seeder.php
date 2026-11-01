<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
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
 * Wildman Open Day 2026 at Sterkfontein Shooting Academy.
 *
 * One-day Compak clay-target open day: Sunday 15 November 2026 at
 * 09:00, R800 per shooter, 100 clays included, braai after (BYO).
 * Contact: Theo Rosema (sales@rosema.co.za / 082 555 0343).
 *
 * The host academy itself runs Compak, Sporting, Trap and Skeet on
 * four distinct ranges near Irene (Pretoria), is members-only
 * Wed–Sun 09:00–16:00, and specialises in corporate shoots (min 20
 * shooters). The seeder captures all four shotgun disciplines on
 * the organisation so Find > Clubs surfaces it correctly.
 *
 * Idempotent — safe to re-run. Keyed on slugs; `firstOrCreate` for
 * the org and venue so curated edits are not overwritten by a later
 * deploy.
 */
class WildmanOpenDay2026Seeder extends Seeder
{
    public function run(): void
    {
        $disciplineSlugs = ['compak-five-stand', 'sporting-clays', 'trap', 'skeet'];

        $disciplines = Discipline::query()
            ->whereIn('slug', $disciplineSlugs)
            ->get()
            ->keyBy('slug');

        $compak = $disciplines->get('compak-five-stand');

        $academy = Organisation::query()->firstOrCreate(
            ['slug' => 'sterkfontein-shooting-academy'],
            [
                'name' => 'Sterkfontein Shooting Academy',
                'short_name' => 'Sterkfontein Academy',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Irene',
                'email' => 'sales@rosema.co.za',
                'phone' => '082 555 0343',
                'description' => "Shotgun club in a picturesque Highveld setting on the edge of a quarry near Irene, Pretoria. Four distinct ranges cover Compak, Sporting, Trap and Skeet, with over 32 top-of-the-range automatic trap machines offering normals, mini's, teals, rabbits, high house and low house targets.\n\nMembers only, Wednesday to Sunday, 09:00–16:00. The clubhouse lapa seats up to 100 and the academy specialises in corporate shoots from 20 competitors upwards. Family friendly.",
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $disciplineIds = $disciplines->pluck('id')->all();

        if ($disciplineIds !== [] && $compak !== null) {
            $academy->syncDisciplines($disciplineIds, $compak->id);
        }

        $venue = Venue::query()->firstOrCreate(
            ['slug' => 'sterkfontein-shooting-academy'],
            [
                'name' => 'Sterkfontein Shooting Academy',
                'province' => Province::Gauteng,
                'town' => 'Irene',
                'access' => VenueAccess::MembersOnly,
                'notes' => 'Four ranges catering for Compak, Sporting, Trap and Skeet. Over 32 automatic trap machines. Clubhouse lapa seats 100.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'wildman-sterkfontein-open-day-2026-11-15'],
            [
                'title' => 'Wildman Open Day — Compak Clays',
                'host_organisation_id' => $academy->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-11-15 09:00:00',
                'ends_at' => null,
                'all_day' => false,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 80000,
                'contact_email' => 'sales@rosema.co.za',
                'entry_payment_instructions' => "Entry: R800 per shooter, includes 100 Compak clays.\nBraai after the shoot — bring your own food and drinks.\n\nBanking: Sterkfontein Shooting Academy, Standard Bank, account 201 851 369. Reference: your Name and Surname.\n\nSend proof of payment to Theo Rosema (sales@rosema.co.za / 082 555 0343) with your full name and squadding preferences.\n\nEntries close 12 November 2026.",
                'description' => "All welcome. Join the shoot and have fun — R800 per shooter includes 100 Compak clays, with a braai after the shoot (bring your own food and drinks).\n\nEntries close 12 November 2026. Contact Theo Rosema on 082 555 0343 or sales@rosema.co.za with your full name and squadding preferences.",
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        if ($compak !== null) {
            $event->syncDisciplines([$compak->id], $compak->id);
        }
    }
}
