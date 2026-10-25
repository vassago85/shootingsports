<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('bumps last_material_change_at when starts_at changes', function (): void {
    $event = Event::factory()->confirmed()->create([
        'last_material_change_at' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

    $event->update(['starts_at' => $event->starts_at->copy()->addDay()]);

    expect($event->fresh()->last_material_change_at?->toDateTimeString())
        ->toBe('2026-10-20 12:00:00');
});

it('bumps last_material_change_at when venue_id changes', function (): void {
    $event = Event::factory()->confirmed()->create([
        'last_material_change_at' => null,
    ]);
    $newVenue = Venue::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-10-21 09:00:00'));

    $event->update(['venue_id' => $newVenue->id]);

    expect($event->fresh()->last_material_change_at?->toDateTimeString())
        ->toBe('2026-10-21 09:00:00');
});

it('bumps last_material_change_at when status changes', function (): void {
    $event = Event::factory()->confirmed()->create([
        'last_material_change_at' => null,
        'status' => EventStatus::Confirmed,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-22 15:30:00'));

    $event->update(['status' => EventStatus::Cancelled]);

    expect($event->fresh()->last_material_change_at?->toDateTimeString())
        ->toBe('2026-10-22 15:30:00');
});

it('does NOT bump the clock on non-material edits', function (): void {
    $event = Event::factory()->confirmed()->create([
        'last_material_change_at' => null,
    ]);

    $event->update([
        'description' => 'Updated prize list and sponsors',
        'entry_fee_cents' => 25000,
    ]);

    expect($event->fresh()->last_material_change_at)->toBeNull();
});

it('exposes last_material_change_at and changed on the saved events endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
    $event = Event::factory()->confirmed()->create();
    $user->savedEvents()->attach($event->id);

    // Material edit after the save
    Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
    $event->update(['starts_at' => $event->starts_at->copy()->addWeek()]);

    $data = test()
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/saved-events')
        ->assertOk()
        ->json('data');

    expect($data[0]['last_material_change_at'])->not->toBeNull()
        ->and($data[0]['changed'])->toBeTrue();
});

it('reports changed=false when the match was edited before the user saved it', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
    $event = Event::factory()->confirmed()->create();
    $event->update(['starts_at' => $event->starts_at->copy()->addWeek()]);

    // User saves after the material change
    Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
    $user->savedEvents()->attach($event->id);

    $data = test()
        ->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/saved-events')
        ->assertOk()
        ->json('data');

    expect($data[0]['changed'])->toBeFalse();
});
