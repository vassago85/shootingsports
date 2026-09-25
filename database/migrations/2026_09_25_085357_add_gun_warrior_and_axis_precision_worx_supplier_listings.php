<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $this->storeLogo('gun-warrior-logo.png', 'provider-logos/gun-warrior.png');
        $this->storeLogo('axis-precision-worx-logo.png', 'provider-logos/axis-precision-worx.png');

        $shared = [
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];

        Provider::query()->updateOrCreate(
            ['slug' => 'gun-warrior'],
            array_merge($shared, [
                'name' => 'Gun Warrior',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::ChassisStocks->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Hennopspark',
                'metro' => GautengMetro::Pretoria,
                'email' => 'info@gunwarrior.co.za',
                'phone' => '012 653 6424',
                'website_url' => 'https://www.gunwarrior.co.za',
                'logo_path' => 'provider-logos/gun-warrior.png',
                'tagline' => 'Rifle chassis, silencers, and firearms in Centurion.',
                'description' => 'Chassis and silencer workshop at 106 Edward Avenue, Hennopspark, Centurion. Visits are by appointment.',
            ]),
        );

        Provider::query()->updateOrCreate(
            ['slug' => 'axis-precision-worx'],
            array_merge($shared, [
                'name' => 'Axis Precision Worx',
                'category' => ProviderCategory::ChassisStocks,
                'services' => [
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::WesternCape,
                'town' => 'Triangle Farm',
                'metro' => null,
                'email' => 'info@axisprecisionworx.com',
                'phone' => '064 692 8888',
                'website_url' => 'https://www.axisprecisionworx.com',
                'logo_path' => 'provider-logos/axis-precision-worx.png',
                'tagline' => 'APW precision chassis, muzzle brakes, and reloading tools in Cape Town.',
                'description' => 'Manufacturer at 3A Micro Street, Triangle Farm, Cape Town, making carbon chassis, muzzle brakes, silencers, and reloading tools.',
            ]),
        );
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', ['gun-warrior', 'axis-precision-worx'])
            ->forceDelete();

        Storage::disk('media')->delete([
            'provider-logos/gun-warrior.png',
            'provider-logos/axis-precision-worx.png',
        ]);
    }

    private function storeLogo(string $filename, string $destination): void
    {
        $source = database_path('seeders/data/'.$filename);

        if (is_file($source)) {
            Storage::disk('media')->put($destination, (string) file_get_contents($source));
        }
    }
};
