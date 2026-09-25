<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Provider::query()->updateOrCreate(
            ['slug' => 'zimbi'],
            [
                'name' => 'Zimbi',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::SafesStorage->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Persequor',
                'metro' => GautengMetro::Pretoria,
                'email' => 'zimbi@zimbi.co.za',
                'phone' => '012 349 1662',
                'website_url' => 'https://zimbi.co.za',
                'tagline' => 'Specialist hunting and firearms shop in Pretoria.',
                'description' => 'Hunting shop at Unit 1A, Persequor Close, 49 De Havilland Crescent, Persequor Techno Park, selling firearms, ammunition, optics, reloading components, and gun safes.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Provider::query()->where('slug', 'zimbi')->forceDelete();
    }
};
