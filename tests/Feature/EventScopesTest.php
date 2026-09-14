<?php

use App\Enums\EventStatus;
use App\Models\Event;

it('excludes drafts from the published scope', function () {
    Event::factory()->draft()->create(['title' => 'Draft match']);
    Event::factory()->confirmed()->create(['title' => 'Confirmed match']);
    Event::factory()->planned()->create(['title' => 'Planned match']);

    $titles = Event::query()->published()->pluck('title');

    expect($titles)->toContain('Confirmed match')
        ->toContain('Planned match')
        ->not->toContain('Draft match');
});

it('returns only future non-terminal events from the upcoming scope', function () {
    Event::factory()->confirmed()->create(['title' => 'Next weekend', 'starts_at' => now()->addWeek()]);
    Event::factory()->confirmed()->past()->create(['title' => 'Last month']);
    Event::factory()->cancelled()->create(['title' => 'Called off', 'starts_at' => now()->addWeek()]);
    Event::factory()->completed()->create(['title' => 'Already shot']);
    Event::factory()->draft()->create(['title' => 'Not public', 'starts_at' => now()->addWeek()]);

    $titles = Event::query()->upcoming()->pluck('title');

    expect($titles)->toContain('Next weekend')
        ->not->toContain('Last month')
        ->not->toContain('Called off')
        ->not->toContain('Already shot')
        ->not->toContain('Not public');
});

it('limits confirmedOnly to confirmed, entries_open and full', function () {
    Event::factory()->confirmed()->create(['title' => 'Locked in']);
    Event::factory()->create(['title' => 'Taking entries', 'status' => EventStatus::EntriesOpen]);
    Event::factory()->create(['title' => 'Cap reached', 'status' => EventStatus::Full]);
    Event::factory()->planned()->create(['title' => 'Still pencilled']);
    Event::factory()->draft()->create(['title' => 'Hidden']);

    $titles = Event::query()->confirmedOnly()->pluck('title');

    expect($titles)->toContain('Locked in')
        ->toContain('Taking entries')
        ->toContain('Cap reached')
        ->not->toContain('Still pencilled')
        ->not->toContain('Hidden');
});

it('treats planned events as provisional', function () {
    $planned = Event::factory()->planned()->create();
    $confirmed = Event::factory()->confirmed()->create();

    expect($planned->isProvisional())->toBeTrue()
        ->and($confirmed->isProvisional())->toBeFalse();
});
