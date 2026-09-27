<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SahrsaEventsSeeder;

it('seeds SAHRSA and the Cape Winelands Open from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SahrsaEventsSeeder::class);

    $host = Organisation::query()->where('slug', 'sahrsa')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Association)
        ->and($host->website_url)->toBe('https://www.sahuntingrifle.co.za');

    $event = Event::query()->where('slug', 'sahrsa-cape-winelands-open-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Cape Winelands Open')
        ->and($event->starts_at->toDateString())->toBe('2026-10-03')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Provincial)
        ->and($event->entry_fee_cents)->toBe(60000)
        ->and($event->member_fee_cents)->toBe(55000)
        ->and($event->entry_url)->toBe('https://www.sahuntingrifle.co.za/events/314')
        ->and($event->banner_path)->toBe('event-banners/sahrsa-cape-winelands-open-2026.png')
        ->and($event->venue?->name)->toBe('Kinga Distillery')
        ->and($event->venue?->town)->toBe('Montagu')
        ->and($event->venue?->province)->toBe(Province::WesternCape)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['hunting-rifle']);

    $this->get(route('events.show', 'sahrsa-cape-winelands-open-2026'))
        ->assertSee('/media/event-banners/sahrsa-cape-winelands-open-2026.png', false)
        ->assertSee('Kinga Distillery');
});

it('seeds the rest of the 2026 SAHRSA programme', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SahrsaEventsSeeder::class);

    $expected = [
        'sahrsa-freestate-open-2026' => ['2026-10-10', 45000, 40000, Province::FreeState, 'sahrsa-freestate-open-2026.png'],
        'sahrsa-noordwes-open-2026' => ['2026-10-17', 50000, 45000, Province::NorthWest, 'sahrsa-noordwes-open-2026.jpg'],
        'sahrsa-cradock-22lr-pcp-2026' => ['2026-10-23', 35000, 30000, Province::EasternCape, 'sahrsa-cradock-22lr-2026.jpg'],
        'sahrsa-cradock-223-2026' => ['2026-10-23', 35000, 30000, Province::EasternCape, 'sahrsa-cradock-223-2026.jpg'],
        'sahrsa-cradock-open-2026' => ['2026-10-24', 55000, 50000, Province::EasternCape, 'sahrsa-cradock-open-2026.jpg'],
    ];

    expect(Event::query()->where('slug', 'sahrsa-league-registration-2026')->exists())->toBeFalse();

    foreach ($expected as $slug => [$date, $entryFee, $memberFee, $province, $banner]) {
        $event = Event::query()->where('slug', $slug)->first();

        expect($event)->not->toBeNull()
            ->and($event->starts_at->toDateString())->toBe($date)
            ->and($event->status)->toBe(EventStatus::EntriesOpen)
            ->and($event->entry_fee_cents)->toBe($entryFee)
            ->and($event->member_fee_cents)->toBe($memberFee)
            ->and($event->entry_url)->toStartWith('https://www.sahuntingrifle.co.za/events/')
            ->and($event->banner_path)->toBe('event-banners/'.$banner)
            ->and($event->venue?->province)->toBe($province)
            ->and($event->disciplines->pluck('slug')->all())->toBe(['hunting-rifle']);
    }
});
