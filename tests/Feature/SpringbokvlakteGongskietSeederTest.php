<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SpringbokvlakteGongskietSeeder;

it('seeds Springbokvlakte Gongskiet from the 31 October 2026 poster', function () {
    $this->travelTo('2026-10-14 09:00:00');
    $this->seed(DisciplineSeeder::class);
    $this->seed(SpringbokvlakteGongskietSeeder::class);

    $host = Organisation::query()->where('slug', 'springbokvlakte-gong-skiet')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Series)
        ->and($host->phone)->toBe('082 828 9734');

    $event = Event::query()->where('slug', 'springbokvlakte-gongskiet-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Gongskiet')
        ->and($event->starts_at->toDateString())->toBe('2026-10-31')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Series)
        ->and($event->entry_fee_cents)->toBe(300000)
        ->and($event->banner_path)->toBe('event-banners/springbokvlakte-gongskiet-2026.png')
        ->and($event->entry_url)->toEndWith('/media/event-entry-forms/springbokvlakte-gongskiet-2026-entry-form.png')
        ->and($event->openForTeamEntries())->toBeTrue()
        ->and($event->team_size)->toBe(4)
        ->and($event->entry_payment_instructions)->toContain('62310133632')
        ->and($event->venue?->name)->toBe('Plaas Middeldoorn')
        ->and($event->venue?->town)->toBe('Mookgophong')
        ->and($event->venue?->province)->toBe(Province::Limpopo)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['gong-shooting']);

    $this->get(route('events.show', 'springbokvlakte-gongskiet-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/springbokvlakte-gongskiet-2026.png', false)
        ->assertSee('/media/event-entry-forms/springbokvlakte-gongskiet-2026-entry-form.png', false)
        ->assertSee('Enter your team')
        ->assertSee('082 828 9734')
        ->assertDontSee('@gmail.com');
});
