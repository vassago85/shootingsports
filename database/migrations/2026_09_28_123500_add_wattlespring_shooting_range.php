<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Venue::query()->updateOrCreate(
            ['slug' => 'wattlespring-sports-shooting-club'],
            [
                'name' => 'Wattlespring Sports Shooting Club',
                'province' => Province::Gauteng,
                'town' => 'Bronkhorstspruit',
                'metro' => GautengMetro::Pretoria,
                'address' => '398 JR Onbekend, Bapsfontein, Bronkhorstspruit, 1001',
                'max_distance_m' => 100,
                'access' => VenueAccess::Public,
                'image_paths' => null,
                'facilities' => [
                    '100 m rifle range',
                    'Seven handgun ranges, including two IPSC stages',
                    'Clay pigeon',
                    'Restaurant and events hall',
                    'Chalets',
                    'Bass dam',
                    'Open Tuesday to Sunday and public holidays, 08:00–16:30. Closed Mondays except during school holidays.',
                    'Rifle range R125 per hour for members and R150 for non-members. Ammunition is not included.',
                ],
                'notes' => 'Phone 076 256 3971. info@wattlespring.co.za. Competitions comps@wattlespring.co.za. Events events@wattlespring.co.za. https://wattlespring.co.za. A proficiency certificate is required to shoot without supervision.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Venue::query()->where('slug', 'wattlespring-sports-shooting-club')->forceDelete();
    }
};
