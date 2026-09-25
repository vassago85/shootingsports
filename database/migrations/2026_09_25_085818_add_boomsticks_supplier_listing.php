<?php

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
        $logoPath = 'provider-logos/boomsticks.png';
        $source = database_path('seeders/data/boomsticks-logo.png');

        if (is_file($source)) {
            Storage::disk('media')->put($logoPath, (string) file_get_contents($source));
        }

        Provider::query()->updateOrCreate(
            ['slug' => 'boomsticks'],
            [
                'name' => 'Boomsticks',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::WesternCape,
                'town' => 'Paarl',
                'email' => 'onlinesales@boomsticks.co.za',
                'phone' => '021 020 0620',
                'website_url' => 'https://boomsticks.co.za',
                'logo_path' => $logoPath,
                'tagline' => 'Firearms, ammunition, optics, and reloading supplies in Paarl.',
                'description' => 'Outdoor shop at 21 Station Street, corner of Tabak Street, Southern Paarl, selling firearms, ammunition, optics, and reloading components.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );
    }

    public function down(): void
    {
        Provider::query()->where('slug', 'boomsticks')->forceDelete();

        Storage::disk('media')->delete('provider-logos/boomsticks.png');
    }
};
