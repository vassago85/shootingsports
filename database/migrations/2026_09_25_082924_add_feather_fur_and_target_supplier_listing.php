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
            ['slug' => 'feather-fur-and-target'],
            [
                'name' => 'Feather Fur and Target',
                'category' => ProviderCategory::ReloadingComponents,
                'services' => [ProviderCategory::Optics->value],
                'province' => Province::Gauteng,
                'town' => 'Faerie Glen',
                'metro' => GautengMetro::Pretoria,
                'email' => 'orders@ffat.co.za',
                'phone' => '087 821 6667',
                'website_url' => 'https://ffat.co.za',
                'tagline' => 'Reloading components, equipment, and optics in Pretoria.',
                'description' => 'Family-owned shop in Faerie Glen selling reloading components, reloading equipment, optics and mounts, and cleaning gear.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Provider::query()->where('slug', 'feather-fur-and-target')->forceDelete();
    }
};
