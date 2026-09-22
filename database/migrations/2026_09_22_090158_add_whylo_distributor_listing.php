<?php

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
            ['slug' => 'whylo'],
            [
                'name' => 'Whylo',
                'category' => ProviderCategory::Distributor,
                'services' => [ProviderCategory::Optics->value],
                'province' => Province::KwaZuluNatal,
                'town' => 'Durban',
                'email' => 'andrew@whylo.co.za',
                'phone' => '+27 31 584 8088',
                'website_url' => 'https://whylo.co.za',
                'description' => 'South African distributor of binoculars, rifle scopes, and hunting accessories.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Provider::query()->where('slug', 'whylo')->forceDelete();
    }
};
