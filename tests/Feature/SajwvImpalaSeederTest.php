<?php

use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SajwvImpalaSeeder;

it('seeds the Impala shotgun day from the October 2026 flyer', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SajwvImpalaSeeder::class);

    $host = Organisation::query()->where('slug', 'sahunters-impala')->first();

    expect($host)->not->toBeNull()
        ->and($host->name)->toBe('SA Jagters Impala')
        ->and($host->type)->toBe(OrganisationType::Club)
        ->and($host->province)->toBe(Province::Gauteng)
        ->and($host->town)->toBe('Bapsfontein')
        ->and($host->email)->toBe('sajwvimpala@gmail.com')
        ->and($host->phone)->toBe('064 154 5175');

    $event = Event::query()->where('slug', 'impala-haelgeweer-skietdag-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Haelgeweer Skietdag')
        ->and($event->starts_at->toDateString())->toBe('2026-10-31')
        ->and($event->all_day)->toBeTrue()
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->publication)->toBe(EventPublication::Published)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(20000)
        ->and($event->target_count)->toBe(25)
        ->and($event->contact_email)->toBe('sajwvimpala@gmail.com')
        ->and($event->banner_path)->toBe('event-banners/sajwv-impala-haelgeweer-skietdag-2026.png')
        ->and($event->venue?->slug)->toBe('wattlespring-sports-shooting-club')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['sporting-clays']);

    $this->get(route('events.show', 'impala-haelgeweer-skietdag-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/sajwv-impala-haelgeweer-skietdag-2026.png', false)
        ->assertSee('Wattlespring')
        ->assertSee('R200')
        ->assertSee('25')
        ->assertSee('064 154 5175')
        ->assertSee('sajwvimpala@gmail.com');
});
