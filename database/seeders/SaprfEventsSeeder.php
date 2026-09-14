<?php

namespace Database\Seeders;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Organisation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SaprfEventsSeeder extends Seeder
{
    public function run(): void
    {
        $prs = Discipline::query()->where('slug', 'prs')->firstOrFail();
        $pr22 = Discipline::query()->where('slug', 'pr22-rimfire')->firstOrFail();
        $precision = Discipline::query()->where('slug', 'precision-rifle')->first();

        $federation = Organisation::query()->updateOrCreate(
            ['slug' => 'saprf'],
            [
                'name' => 'South African Precision Rifle Federation',
                'short_name' => 'SAPRF',
                'type' => OrganisationType::Federation,
                'province' => null,
                'town' => null,
                'website_url' => 'https://saprf.co.za',
                'description' => 'The official competition platform for PRS and PR22 precision rifle — memberships, match management, scoring, and national standings.',
                'logo_path' => $this->storeLogo(),
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        $disciplineIds = array_values(array_filter([
            $precision?->id,
            $prs->id,
            $pr22->id,
        ]));
        $federation->syncDisciplines($disciplineIds, $precision?->id ?? $prs->id);
    }

    private function storeLogo(): ?string
    {
        $source = database_path('seeders/data/saprf-logo.png');

        if (! is_file($source)) {
            return Organisation::query()->where('slug', 'saprf')->value('logo_path');
        }

        $path = 'organisation-logos/saprf.png';
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
