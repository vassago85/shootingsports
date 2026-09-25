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
        $logoPath = 'provider-logos/dave-sheer-guns-cape-town.png';
        $source = database_path('seeders/data/dave-sheer-guns-cape-town-logo.png');

        if (is_file($source)) {
            Storage::disk('media')->put($logoPath, (string) file_get_contents($source));
        }

        $shared = [
            'category' => ProviderCategory::Dealer,
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];

        Provider::query()->updateOrCreate(
            ['slug' => 'dave-sheer-guns-johannesburg'],
            array_merge($shared, [
                'name' => 'Dave Sheer Guns Johannesburg',
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Gunsmith->value,
                    ProviderCategory::Instructor->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Bramley',
                'metro' => GautengMetro::Johannesburg,
                'email' => null,
                'phone' => '011 440 0345',
                'website_url' => 'https://davesheer.com',
                'tagline' => 'Firearms, ammunition, gunsmithing, and training in Johannesburg.',
                'description' => 'Gun shop at 95 Forest Road, Bramley, selling firearms and ammunition, with repairs and training.',
            ]),
        );

        Provider::query()->updateOrCreate(
            ['slug' => 'dave-sheer-guns-cape-town'],
            array_merge($shared, [
                'name' => 'Dave Sheer Guns Cape Town',
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::SafesStorage->value,
                    ProviderCategory::Gunsmith->value,
                    ProviderCategory::Instructor->value,
                ],
                'province' => Province::WesternCape,
                'town' => 'Durbanville',
                'metro' => null,
                'email' => 'admin@davesheerct.com',
                'phone' => '021 007 2920',
                'website_url' => 'https://davesheerct.com',
                'logo_path' => $logoPath,
                'tagline' => 'Firearms, ammunition, optics, gunsmithing, and an on-site range in Durbanville.',
                'description' => 'Gun shop at Shop 35A-D, Groot Phesantekraal View, corner of Klipheuwel and Okavango Drive, Durbanville, with an on-site range, optics, gun safes, and a workshop.',
            ]),
        );
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', ['dave-sheer-guns-johannesburg', 'dave-sheer-guns-cape-town'])
            ->forceDelete();

        Storage::disk('media')->delete('provider-logos/dave-sheer-guns-cape-town.png');
    }
};
