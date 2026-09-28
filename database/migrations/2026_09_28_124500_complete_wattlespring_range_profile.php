<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('notes');
            $table->string('website_url')->nullable()->after('description');
        });

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
                'website_url' => 'https://wattlespring.co.za',
                'description' => <<<'TEXT'
Wattlespring Sports Shooting Club is on the farm Onbekend at Bapsfontein, east of Pretoria. The grounds have a 100 m rifle range, seven handgun ranges with two permanent IPSC stages, and clay pigeon fields.

The rifle range is R125 per hour for members and R150 for non-members. Ammunition is not included. A proficiency certificate is required to shoot without supervision.

The club also has a restaurant and events hall, chalets, and a bass dam. It is open Tuesday to Sunday and on public holidays, from 08:00 to 16:30, and closed on Mondays except during school holidays.
TEXT,
                'facilities' => [
                    '100 m rifle range',
                    'Seven handgun ranges',
                    'Two IPSC stages',
                    'Clay pigeon',
                    'Restaurant and events hall',
                    'Chalets',
                    'Bass dam',
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
        Venue::query()
            ->where('slug', 'wattlespring-sports-shooting-club')
            ->update([
                'description' => null,
                'website_url' => null,
            ]);

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn(['description', 'website_url']);
        });
    }
};
