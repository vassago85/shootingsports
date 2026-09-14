<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\NgGemeenteDelmasSeeder;

it('seeds the NG Delmas Gongskiet from the 2027 poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(NgGemeenteDelmasSeeder::class);

    $host = Organisation::query()->where('slug', 'ng-gemeente-delmas')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Club);

    $event = Event::query()->where('slug', 'ng-delmas-gongskiet-2027')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('NG Gemeente Delmas Gongskiet')
        ->and($event->starts_at->toDateString())->toBe('2027-01-30')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->capacity)->toBe(60)
        ->and($event->entry_fee_cents)->toBe(75000)
        ->and($event->banner_path)->toBe('event-banners/ng-delmas-gongskiet-2027.png')
        ->and($event->venue?->name)->toBe('Plaas Witklipbank')
        ->and($event->venue?->town)->toBe('Delmas')
        ->and($event->venue?->province)->toBe(Province::Mpumalanga)
        ->and($event->hostOrganisation?->email)->toBe('william@gunshopdelmas.co.za')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['gong-shooting']);

    $this->get(route('matches.show', 'ng-delmas-gongskiet-2027'))
        ->assertOk()
        ->assertSee('/media/event-banners/ng-delmas-gongskiet-2027.png', false)
        ->assertSee('Plaas Witklipbank')
        ->assertSee('william@gunshopdelmas.co.za')
        ->assertSee('60 teams');
});
