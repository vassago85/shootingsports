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
            ['slug' => 'mc-tactical'],
            [
                'name' => 'MC Tactical',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::ChassisStocks->value,
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Garsfontein',
                'metro' => GautengMetro::Pretoria,
                'email' => 'sales@mctactical.co.za',
                'phone' => '082 821 0420',
                'website_url' => 'https://mctactical.co.za',
                'tagline' => 'Firearms, optics, chassis, and reloading supplies in Pretoria.',
                'description' => 'Gun shop at 873 Patryshond Street, Garsfontein, selling firearms, scopes, chassis and stocks, and reloading components.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Provider::query()->where('slug', 'mc-tactical')->forceDelete();
    }
};
