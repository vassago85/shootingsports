<?php

use App\Enums\OrganisationType;
use App\Models\Organisation;
use Database\Seeders\BenchrestFederationsSeeder;
use Database\Seeders\DisciplineSeeder;

it('seeds SAPSSF and WRABF as published benchrest federations', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(BenchrestFederationsSeeder::class);
    $this->seed(BenchrestFederationsSeeder::class);

    expect(Organisation::query()->whereIn('slug', ['sapssf', 'wrabf'])->count())->toBe(2);

    $sapssf = Organisation::query()->where('slug', 'sapssf')->first();

    expect($sapssf)->not->toBeNull()
        ->and($sapssf->name)->toBe('South African Precision Sport Shooting Federation')
        ->and($sapssf->short_name)->toBe('SAPSSF')
        ->and($sapssf->type)->toBe(OrganisationType::Federation)
        ->and($sapssf->province)->toBeNull()
        ->and($sapssf->accredited)->toBeTrue()
        ->and($sapssf->website_url)->toBe('https://www.sapssf.com/')
        ->and($sapssf->disciplines->pluck('slug')->sort()->values()->all())->toBe(['benchrest', 'benchrest-airgun'])
        ->and($sapssf->disciplines->firstWhere('pivot.is_primary', true)?->slug)->toBe('benchrest');

    $wrabf = Organisation::query()->where('slug', 'wrabf')->first();

    expect($wrabf)->not->toBeNull()
        ->and($wrabf->name)->toBe('World Rimfire and Air Rifle Benchrest Federation')
        ->and($wrabf->short_name)->toBe('WRABF')
        ->and($wrabf->type)->toBe(OrganisationType::Federation)
        ->and($wrabf->accredited)->toBeFalse()
        ->and($wrabf->email)->toBe('secretary@wrabf.com')
        ->and($wrabf->website_url)->toBe('https://www.wrabf.com/')
        ->and($wrabf->facebook_url)->toBe('https://www.facebook.com/groups/WRABF/')
        ->and($wrabf->disciplines->pluck('slug')->sort()->values()->all())->toBe(['benchrest', 'benchrest-airgun']);

    $this->get(route('clubs.show', 'sapssf'))->assertNotFound();

    $this->get(route('federations.show', 'sapssf'))
        ->assertSee('South African Precision Sport Shooting Federation')
        ->assertSee('SAPS accredited national federation')
        ->assertSee('www.sapssf.com')
        ->assertSee('Benchrest')
        ->assertSee('Benchrest Airgun');

    $this->get(route('federations.show', 'wrabf'))
        ->assertSee('World Rimfire and Air Rifle Benchrest Federation')
        ->assertSee('www.wrabf.com')
        ->assertSee('https://www.facebook.com/groups/WRABF/', false);
});
