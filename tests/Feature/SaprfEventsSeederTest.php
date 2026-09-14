<?php

use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SaprfEventsSeeder;

it('seeds the SAPRF federation without inventing match rows', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SaprfEventsSeeder::class);

    $federation = Organisation::query()->where('slug', 'saprf')->first();

    expect($federation)->not->toBeNull()
        ->and($federation->type)->toBe(OrganisationType::Federation)
        ->and($federation->website_url)->toBe('https://saprf.co.za')
        ->and($federation->logo_path)->toBe('organisation-logos/saprf.png')
        ->and($federation->disciplines->pluck('slug')->all())
        ->toContain('precision-rifle', 'prs', 'pr22-rimfire');

    expect(Event::query()->where('host_organisation_id', $federation->id)->count())->toBe(0);
});
