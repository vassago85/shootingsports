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
use Illuminate\Support\Facades\Storage;

class PmpscCombinedShootSeeder extends Seeder
{
    public function run(): void
    {
        $pistol = Discipline::query()->where('slug', 'ipsc-practical')->firstOrFail();
        $rifle = Discipline::query()->where('slug', 'combat-rifle')->firstOrFail();

        $club = Organisation::query()->updateOrCreate(
            ['slug' => 'pretoria-military-practical-shooting-club'],
            [
                'name' => 'Pretoria Military Practical Shooting Club',
                'short_name' => 'PMPSC',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'email' => 'stats.pmpsc@gmail.com',
                'website_url' => 'https://www.pmpsc.co.za/',
                'description' => 'PMPSC is a military practical shooting club affiliated to the North Gauteng Practical Shooting Association and the South African Practical Shooting Association. Home range is Eeufees Shooting Range.',
                'logo_path' => $this->store('pmpsc-logo.png', 'organisation-logos/pmpsc.png', 'pretoria-military-practical-shooting-club', Organisation::class),
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $club->syncDisciplines([$pistol->id, $rifle->id], $pistol->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'eeufees-shooting-range'],
            [
                'name' => 'Eeufees Shooting Range',
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'address' => 'Eeufees Road, Pretoria',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $venue->syncDisciplines([$pistol->id, $rifle->id], $pistol->id);

        $event = Event::query()->updateOrCreate(
            ['slug' => 'pmpsc-mpds-fosa-combined-shoot-2026-09-19'],
            [
                'title' => 'MPDS & FOSA Combined Shoot',
                'host_organisation_id' => $club->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-09-19 07:00:00',
                'ends_at' => null,
                'all_day' => false,
                'level' => EventLevel::Club,
                'status' => EventStatus::Confirmed,
                'confirmed_at' => now(),
                'entry_fee_cents' => 10000,
                'round_count' => 74,
                'stage_count' => 5,
                'description' => "Presented by MPDS (Multi Platform Dimension Shooting) and FOSA (Firearm Owners South Africa) at Eeufees Shooting Range.\n\nSaturday 19 September 2026. R100 for the day.\n\n07:00 registration and final squadding\n07:30 range briefing\n08:00 start shooting\n\nRifle: two stages, minimum 31 rounds.\nPistol: three stages, minimum 43 rounds.",
                'banner_path' => $this->store('pmpsc-combined-shoot-2026-09-19.png', 'event-banners/pmpsc-combined-shoot-2026-09-19.png', 'pmpsc-mpds-fosa-combined-shoot-2026-09-19', Event::class),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$pistol->id, $rifle->id], $pistol->id);
    }

    /**
     * @param  class-string<Organisation|Event>  $model
     */
    private function store(string $filename, string $destination, string $slug, string $model): ?string
    {
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return $model::query()->where('slug', $slug)->value(
                str_starts_with($destination, 'organisation-logos/') ? 'logo_path' : 'banner_path',
            );
        }

        Storage::disk('media')->put($destination, file_get_contents($source));

        return $destination;
    }
}
