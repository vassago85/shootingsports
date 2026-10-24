<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventPublication;
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
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * SAJWV / SAHGCA Impala branch shotgun day, from the October 2026 flyer.
 *
 * The shoot is at the existing Wattlespring range in Bapsfontein, next
 * to Blue Gum Valley. The flyer does not name a start time.
 */
class SajwvImpalaSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'sporting-clays')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'sahunters-impala'],
            [
                'name' => 'SA Jagters Impala',
                'short_name' => 'Impala',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Bapsfontein',
                'email' => 'sajwvimpala@gmail.com',
                'phone' => '064 154 5175',
                'description' => 'SA Jagters (SAJWV / SAHGCA) Impala branch. Phone Oppies on 064 154 5175.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = $this->wattlespring();

        $event = Event::query()->updateOrCreate(
            ['slug' => 'impala-haelgeweer-skietdag-2026'],
            [
                'title' => 'Haelgeweer Skietdag',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-10-31 00:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'publication' => EventPublication::Published,
                'confirmed_at' => now(),
                'entry_fee_cents' => 20000,
                'target_count' => 25,
                'entry_url' => null,
                'contact_email' => 'sajwvimpala@gmail.com',
                'description' => 'Saturday 31 October 2026 at Wattlespring Sport Club, Bapsfontein, on the sport shooting ground next to Blue Gum Valley Shooting Club. SA Jagters (SAJWV / SAHGCA) Impala branch shotgun day. R200 for 25 clays. Phone Oppies on 064 154 5175 or email sajwvimpala@gmail.com.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    /**
     * Wattlespring is already in the directory. After the range merge
     * only wattlespring-sport-shooting-club stays published. A fresh
     * database still has the original wattlespring-sports-shooting-club
     * profile, which is the one to keep when both rows are published.
     */
    private function wattlespring(): Venue
    {
        $published = Venue::query()
            ->where('status', ListingStatus::Published)
            ->whereIn('slug', [
                'wattlespring-sports-shooting-club',
                'wattlespring-sport-shooting-club',
            ])
            ->get()
            ->keyBy('slug');

        $venue = $published->get('wattlespring-sports-shooting-club')
            ?? $published->get('wattlespring-sport-shooting-club');

        if (! $venue instanceof Venue) {
            throw new RuntimeException('Wattlespring range is not seeded.');
        }

        return $venue;
    }

    private function storeBanner(): ?string
    {
        $filename = 'sajwv-impala-haelgeweer-skietdag-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'impala-haelgeweer-skietdag-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
