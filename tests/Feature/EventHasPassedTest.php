<?php

use App\Enums\EventStatus;
use App\Models\Event;

it('reports future events as not passed', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(6),
        'status' => EventStatus::Confirmed,
    ]);

    expect($event->hasPassed())->toBeFalse();
});

it('reports single-day events as passed after the day', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'status' => EventStatus::Confirmed,
    ]);

    expect($event->hasPassed())->toBeTrue();
});

it('keeps multi-day events "not passed" while ends_at is still in the future', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'status' => EventStatus::Confirmed,
    ]);

    expect($event->hasPassed())->toBeFalse();
});

it('treats Cancelled or Completed events as passed regardless of date', function () {
    $future = now()->addWeek();

    $cancelled = Event::factory()->create([
        'starts_at' => $future,
        'ends_at' => $future,
        'status' => EventStatus::Cancelled,
    ]);
    $completed = Event::factory()->create([
        'starts_at' => $future,
        'ends_at' => $future,
        'status' => EventStatus::Completed,
    ]);

    expect($cancelled->hasPassed())->toBeTrue()
        ->and($completed->hasPassed())->toBeTrue();
});
