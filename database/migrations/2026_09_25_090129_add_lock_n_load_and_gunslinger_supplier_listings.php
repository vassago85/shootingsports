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
        $this->storeLogo('lock-n-load-logo.png', 'provider-logos/lock-n-load.png');
        $this->storeLogo('gunslinger-logo.png', 'provider-logos/gunslinger.png');

        $shared = [
            'category' => ProviderCategory::Dealer,
            'province' => Province::Gauteng,
            'town' => 'Erasmuskloof',
            'metro' => GautengMetro::Pretoria,
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];

        Provider::query()->updateOrCreate(
            ['slug' => 'lock-n-load'],
            array_merge($shared, [
                'name' => "Lock 'n Load",
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::ChassisStocks->value,
                    ProviderCategory::Gunsmith->value,
                ],
                'email' => 'info@locknload.pro',
                'phone' => '062 075 9670',
                'website_url' => 'https://locknload.pro',
                'logo_path' => 'provider-logos/lock-n-load.png',
                'tagline' => 'Bespoke firearms, optics, stocks, and gunsmithing in Pretoria.',
                'description' => 'Bespoke gun shop at 461 Lois Avenue, Erasmuskloof, selling firearms, optics, reloading equipment, and rifle stocks, with a gunsmithing workshop.',
            ]),
        );

        Provider::query()->updateOrCreate(
            ['slug' => 'gunslinger'],
            array_merge($shared, [
                'name' => 'Gunslinger',
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::ChassisStocks->value,
                ],
                'email' => 'sales@gunslinger.bz',
                'phone' => '079 513 5099',
                'website_url' => 'https://gunslinger.bz',
                'logo_path' => 'provider-logos/gunslinger.png',
                'tagline' => 'Precision firearms, optics, ammunition, and rifle stocks in Pretoria.',
                'description' => 'Firearms dealer and importer at 461 Lois Avenue, Erasmuskloof, supplying precision rifles, optics, ammunition, and rifle stocks.',
            ]),
        );
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', ['lock-n-load', 'gunslinger'])
            ->forceDelete();

        Storage::disk('media')->delete([
            'provider-logos/lock-n-load.png',
            'provider-logos/gunslinger.png',
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
