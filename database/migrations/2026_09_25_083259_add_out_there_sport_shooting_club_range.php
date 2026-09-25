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
            ['slug' => 'out-there-sport-shooting-club'],
            [
                'name' => 'Out There Sport Shooting Club',
                'province' => Province::Gauteng,
                'town' => 'Bashewa',
                'metro' => GautengMetro::Pretoria,
                'address' => 'Moria Saai Farm, Garsfontein Road, Bashewa, Pretoria, 0056',
                'max_distance_m' => 300,
                'access' => VenueAccess::Public,
                'day_fee_cents' => 15000,
                'notes' => 'Bookings required. Day fee R150; members shoot free. Rifle ranges to 300 m, handgun bays, three trap fields, and a bow range. Office 012 945 5152. rangebookings@outthereadventures.co.za',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Venue::query()->where('slug', 'out-there-sport-shooting-club')->forceDelete();
    }
};
