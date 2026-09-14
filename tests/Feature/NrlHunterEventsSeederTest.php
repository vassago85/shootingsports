<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\NrlHunterEventsSeeder;

it('seeds NRL Hunter SA and the Season 6 matches from the posters', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(NrlHunterEventsSeeder::class);

    $host = Organisation::query()->where('slug', 'nrl-hunter-sa')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Association)
        ->and($host->website_url)->toBe('https://www.nrlhuntersa.org');

    $event = Event::query()->where('slug', 'nrl-hunter-highlands-hunter-2027')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('The Highlands Hunter')
        ->and($event->starts_at->toDateString())->toBe('2027-01-30')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Series)
        ->and($event->entry_url)->toBe('https://practiscore.com/nrl-hunter-the-highlands-hunter-clone/register')
        ->and($event->banner_path)->toBe('event-banners/nrl-hunter-highlands-hunter-2027.png')
        ->and($event->venue?->name)->toBe('Kleine Weide')
        ->and($event->venue?->town)->toBe('Somerset East')
        ->and($event->venue?->province)->toBe(Province::EasternCape)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['nrl-hunter']);

    $ribbok = Event::query()->where('slug', 'nrl-hunter-die-ribbok-jagter-2026')->first();

    expect($ribbok)->not->toBeNull()
        ->and($ribbok->title)->toBe('Die Ribbok Jagter')
        ->and($ribbok->starts_at->toDateString())->toBe('2026-10-03')
        ->and($ribbok->status)->toBe(EventStatus::EntriesOpen)
        ->and($ribbok->entry_url)->toBe('https://practiscore.com/nrl-hunter-die-ribbok-jagter-s6/register')
        ->and($ribbok->banner_path)->toBe('event-banners/nrl-hunter-die-ribbok-jagter-2026.png')
        ->and($ribbok->venue?->name)->toBe('Elandsberg')
        ->and($ribbok->venue?->town)->toBe('Strydenburg')
        ->and($ribbok->venue?->province)->toBe(Province::NorthernCape);

    $frontier = Event::query()->where('slug', 'nrl-hunter-frontier-faceoff-2027')->first();

    expect($frontier)->not->toBeNull()
        ->and($frontier->title)->toBe('Frontier Face-Off')
        ->and($frontier->starts_at->toDateString())->toBe('2027-04-03')
        ->and($frontier->status)->toBe(EventStatus::EntriesOpen)
        ->and($frontier->entry_url)->toBe('https://practiscore.com/nrl-hunter-frontier-faceoff-s6/register')
        ->and($frontier->banner_path)->toBe('event-banners/nrl-hunter-frontier-faceoff-2027.png')
        ->and($frontier->venue?->name)->toBe('Cintsa')
        ->and($frontier->venue?->town)->toBe('East London')
        ->and($frontier->venue?->province)->toBe(Province::EasternCape);

    $farmFlair = Event::query()->where('slug', 'nrl-hunter-farm-flair-wild-west-2026')->first();

    expect($farmFlair)->not->toBeNull()
        ->and($farmFlair->title)->toBe('Farm Flair Wild West')
        ->and($farmFlair->starts_at->toDateString())->toBe('2026-11-21')
        ->and($farmFlair->status)->toBe(EventStatus::EntriesOpen)
        ->and($farmFlair->entry_url)->toBe('https://practiscore.com/nrl-hunter-farm-flair-wild-west/register')
        ->and($farmFlair->banner_path)->toBe('event-banners/nrl-hunter-farm-flair-wild-west-2026.png')
        ->and($farmFlair->venue?->name)->toBe('Palmietfontein')
        ->and($farmFlair->venue?->town)->toBe('Wolmaransstad')
        ->and($farmFlair->venue?->province)->toBe(Province::NorthWest);

    $kwaThabileng = Event::query()->where('slug', 'nrl-hunter-kwa-thabileng-2027')->first();

    expect($kwaThabileng)->not->toBeNull()
        ->and($kwaThabileng->title)->toBe('Kwa Thabileng Lodge')
        ->and($kwaThabileng->starts_at->toDateString())->toBe('2027-03-13')
        ->and($kwaThabileng->status)->toBe(EventStatus::EntriesOpen)
        ->and($kwaThabileng->entry_url)->toBe('https://practiscore.com/nrl-hunter-kwathabileng-hunter/register')
        ->and($kwaThabileng->banner_path)->toBe('event-banners/nrl-hunter-kwa-thabileng-2027.png')
        ->and($kwaThabileng->venue?->name)->toBe('Kwa Thabileng Lodge')
        ->and($kwaThabileng->venue?->town)->toBe('Reitz')
        ->and($kwaThabileng->venue?->province)->toBe(Province::FreeState);

    $this->get(route('matches.show', 'nrl-hunter-kwa-thabileng-2027'))
        ->assertOk()
        ->assertSee('/media/event-banners/nrl-hunter-kwa-thabileng-2027.png', false);
});
