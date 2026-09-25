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
            ['slug' => 'blue-gum-valley-shooting-range'],
            [
                'name' => 'Blue Gum Valley Shooting Range',
                'province' => Province::Gauteng,
                'town' => 'Bronkhorstspruit',
                'metro' => GautengMetro::Pretoria,
                'address' => '30 Knoppiesfontein Road, Onbekend, Bronkhorstspruit',
                'access' => VenueAccess::Public,
                'notes' => 'Phone 082 412 9425. Open daily 08:00–16:00. Outdoor range past Bapsfontein, with toilets and a braai area.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Venue::query()->where('slug', 'blue-gum-valley-shooting-range')->forceDelete();
    }
};
