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
 * Royal Flush Steel Challenge 2026 series at Legends Adventure Farm.
 *
 * Idempotent — safe to re-run. Keyed on slugs so re-seeding only
 * patches the fields we set (does not clobber `logo_path`, claims or
 * admin edits to other columns). The series organisation uses
 * `firstOrCreate` so a manually curated listing is left alone; the
 * matches use `updateOrCreate` so date / status corrections flow
 * through on each deploy.
 *
 * Season scoring is best 3 match scores plus the final match ×2
 * towards the overall season total; captured in the series
 * description so visitors to the public club page see the rule.
 */
class RoyalFlushSteelChallenge2026Seeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'steel-challenge')->first();

        $series = Organisation::query()->firstOrCreate(
            ['slug' => 'royal-flush-steel-challenge'],
            [
                'name' => 'Royal Flush Steel Challenge',
                'short_name' => 'Royal Flush',
                'type' => OrganisationType::Series,
                'province' => Province::Gauteng,
                'town' => 'Rayton',
                'facebook_url' => 'https://www.facebook.com/share/183JfPu4ma/',
                'description' => 'Monthly Steel Challenge series hosted at Legends Adventure Farm, Rayton. Season scoring: your best three match scores plus the final match counted twice contribute to the overall season total.',
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        // Patch the social link + description on existing rows (does
        // not change claims, logos, or other admin-managed fields).
        $series->forceFill([
            'facebook_url' => $series->facebook_url ?: 'https://www.facebook.com/share/183JfPu4ma/',
            'description' => $series->description ?: 'Monthly Steel Challenge series hosted at Legends Adventure Farm, Rayton. Season scoring: your best three match scores plus the final match counted twice contribute to the overall season total.',
        ])->save();

        if ($discipline !== null) {
            $series->syncDisciplines([$discipline->id], $discipline->id);
        }

        $venue = Venue::query()->firstOrCreate(
            ['slug' => 'legends-adventure-farm'],
            [
                'name' => 'Legends Adventure Farm',
                'province' => Province::Gauteng,
                'town' => 'Rayton',
                'access' => VenueAccess::GuestByArrangement,
                'notes' => 'Steel Challenge bays used by the Royal Flush monthly series.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        foreach ($this->matches() as $match) {
            $isFinal = $match['final'] ?? false;

            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $series->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $match['starts_at'],
                    'ends_at' => null,
                    'all_day' => true,
                    'level' => EventLevel::Series,
                    'status' => EventStatus::Confirmed,
                    'confirmed_at' => now(),
                    'description' => $isFinal
                        ? 'Final leg of the 2026 Royal Flush Steel Challenge season. Score counts double towards the overall season total.'
                        : 'Monthly leg of the 2026 Royal Flush Steel Challenge season at Legends Adventure Farm.',
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            if ($discipline !== null) {
                $event->syncDisciplines([$discipline->id], $discipline->id);
            }
        }
    }

    /**
     * @return list<array{slug: string, title: string, starts_at: string, final?: true}>
     */
    private function matches(): array
    {
        return [
            ['slug' => 'royal-flush-steel-challenge-2026-01-31', 'title' => 'Royal Flush Steel Challenge — Match 1', 'starts_at' => '2026-01-31 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-02-21', 'title' => 'Royal Flush Steel Challenge — Match 2', 'starts_at' => '2026-02-21 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-03-28', 'title' => 'Royal Flush Steel Challenge — Match 3', 'starts_at' => '2026-03-28 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-04-18', 'title' => 'Royal Flush Steel Challenge — Match 4', 'starts_at' => '2026-04-18 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-05-23', 'title' => 'Royal Flush Steel Challenge — Match 5', 'starts_at' => '2026-05-23 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-06-27', 'title' => 'Royal Flush Steel Challenge — Match 6', 'starts_at' => '2026-06-27 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-07-18', 'title' => 'Royal Flush Steel Challenge — Match 7', 'starts_at' => '2026-07-18 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-08-22', 'title' => 'Royal Flush Steel Challenge — Match 8', 'starts_at' => '2026-08-22 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-09-05', 'title' => 'Royal Flush Steel Challenge — Match 9', 'starts_at' => '2026-09-05 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-10-03', 'title' => 'Royal Flush Steel Challenge — Match 10', 'starts_at' => '2026-10-03 08:00:00'],
            ['slug' => 'royal-flush-steel-challenge-2026-11-28', 'title' => 'Royal Flush Steel Challenge — Final', 'starts_at' => '2026-11-28 08:00:00', 'final' => true],
        ];
    }
}
