<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\AgriBoKammanassieSeeder;
use Database\Seeders\DisciplineSeeder;

it('seeds the Agri Bo-Kammanassie 2-man gong challenge from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(AgriBoKammanassieSeeder::class);

    $host = Organisation::query()->where('slug', 'agri-bo-kammanassie')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Association)
        ->and($host->phone)->toBe('082 400 4155')
        ->and($host->email)->toBe('gert@dutoit.com');

    $event = Event::query()->where('slug', 'agri-bo-kammanassie-2-man-gong-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('2-Man Gong Challenge')
        ->and($event->starts_at->toDateString())->toBe('2026-10-10')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(80000)
        ->and($event->round_count)->toBe(30)
        ->and($event->banner_path)->toBe('event-banners/agri-bo-kammanassie-2-man-gong-2026.png')
        ->and($event->venue?->name)->toBe('Meulrivier')
        ->and($event->venue?->town)->toBe('Langkloof')
        ->and($event->venue?->province)->toBe(Province::WesternCape)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['gong-shooting']);

    $this->get(route('matches.show', 'agri-bo-kammanassie-2-man-gong-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/agri-bo-kammanassie-2-man-gong-2026.png', false)
        ->assertSee('Meulrivier');
});
