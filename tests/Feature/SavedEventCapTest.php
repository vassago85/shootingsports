<?php

use App\Enums\Plan;
use App\Exceptions\PlanLimitExceeded;
use App\Livewire\SaveToCalendar;
use App\Models\Event;
use App\Models\SavedEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('blocks a free user from saving a fourth match', function () {
    $user = User::factory()->create();
    $events = Event::factory()->count(4)->create();

    foreach ($events->take(3) as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    expect(fn () => SavedEvent::create([
        'user_id' => $user->id,
        'event_id' => $events->last()->id,
    ]))->toThrow(PlanLimitExceeded::class);

    expect(SavedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('lets a pro user save past the free cap', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $events = Event::factory()->count(4)->create();

    foreach ($events as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    expect(SavedEvent::query()->where('user_id', $user->id)->count())->toBe(4);
});

it('treats an expired pro plan as the free cap', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->subDay(),
    ]);
    $events = Event::factory()->count(4)->create();

    foreach ($events->take(3) as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    expect(fn () => SavedEvent::create([
        'user_id' => $user->id,
        'event_id' => $events->last()->id,
    ]))->toThrow(PlanLimitExceeded::class);
});

it('opens the upgrade prompt when a free user saves past the cap on the web', function () {
    $user = User::factory()->create();
    $events = Event::factory()->count(4)->create();

    foreach ($events->take(3) as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    Livewire::actingAs($user)
        ->test(SaveToCalendar::class, ['event' => $events->last()])
        ->call('toggle')
        ->assertDispatched('open-upgrade-prompt', trigger: 'saved_events_limit')
        ->assertSet('saved', false);

    expect(SavedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('returns a plan-limit code when the app saves past the cap', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $events = Event::factory()->count(4)->create();

    foreach ($events->take(3) as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/me/saved-events', ['event_id' => $events->last()->id])
        ->assertForbidden()
        ->assertJson([
            'code' => 'saved_events_limit',
            'trigger' => 'saved_events_limit',
            'limit' => 3,
            'current' => 3,
        ]);

    expect(SavedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('still accepts a repeat save once the free cap is full', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $events = Event::factory()->count(3)->create();

    foreach ($events as $event) {
        SavedEvent::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/me/saved-events', ['event_id' => $events->first()->id])
        ->assertCreated()
        ->assertJson(['saved' => true]);

    expect(SavedEvent::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('does not describe a personal calendar as a pro feature', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('custom-calendar'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('my-calendar'))
        ->assertOk()
        ->assertDontSee('A personal calendar is a Pro feature')
        ->assertSee('Reset my calendar link');
});
