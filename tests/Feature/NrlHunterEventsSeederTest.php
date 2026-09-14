<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\NrlHunterEventsSeeder;

it('seeds NRL Hunter SA and The Highlands Hunter from the Season 6 poster', function () {
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
});
