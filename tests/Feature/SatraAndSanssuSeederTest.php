<?php

use App\Enums\OrganisationType;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SatraAndSanssuSeeder;

it('seeds SATRA and SANSSU as findable national associations', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SatraAndSanssuSeeder::class);
    $this->seed(SatraAndSanssuSeeder::class);

    expect(Organisation::query()->whereIn('slug', ['satra', 'sanssu'])->count())->toBe(2);

    $satra = Organisation::query()->where('slug', 'satra')->first();

    expect($satra)->not->toBeNull()
        ->and($satra->name)->toBe('South African Target Rifle Association')
        ->and($satra->short_name)->toBe('SATRA')
        ->and($satra->type)->toBe(OrganisationType::Association)
        ->and($satra->province)->toBeNull()
        ->and($satra->email)->toBe('satra.22skiet@gmail.com')
        ->and($satra->website_url)->toBe('https://www.satra.org.za/')
        ->and($satra->facebook_url)->toBe('https://www.facebook.com/profile.php?id=100073739451678')
        ->and($satra->disciplines->pluck('slug')->sort()->values()->all())->toBe([
            '10m-air-rifle',
            'f-class',
            'hunting-rifle',
            'target-rifle',
        ])
        ->and($satra->disciplines->firstWhere('pivot.is_primary', true)?->slug)->toBe('target-rifle');

    $sanssu = Organisation::query()->where('slug', 'sanssu')->first();

    expect($sanssu)->not->toBeNull()
        ->and($sanssu->name)->toBe('South African National School Shooter Union')
        ->and($sanssu->short_name)->toBe('SANSSU')
        ->and($sanssu->type)->toBe(OrganisationType::Association)
        ->and($sanssu->email)->toBe('sekretaresse@sanssu.co.za')
        ->and($sanssu->website_url)->toBe('https://sanssu.co.za/')
        ->and($sanssu->disciplines->pluck('slug')->all())->toBe(['10m-air-rifle']);

    $this->get(route('clubs.show', 'satra'))
        ->assertSee('South African Target Rifle Association')
        ->assertSee('www.satra.org.za');

    $this->get(route('clubs.index', ['q' => 'SANSSU']))
        ->assertSee('South African National School Shooter Union')
        ->assertDontSee('South African Target Rifle Association');

    $this->get(route('glossary'))
        ->assertSee('listed separately');
});
