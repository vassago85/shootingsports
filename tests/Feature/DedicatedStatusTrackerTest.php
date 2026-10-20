<?php

use App\Enums\Plan;
use App\Livewire\Settings\DedicatedStatus;
use App\Models\AttendedEvent;
use App\Models\Event;
use App\Models\Flag;
use App\Models\User;
use Livewire\Livewire;

/*
 * Dedicated Status Tracker: Pro-only renewal activity widget.
 *
 * Shooter sets body + renewal date + required count. Widget shows
 * progress based on attendance log entries whose linked event carries
 * the "counts-toward-activity-requirements" flag within the current
 * renewal window.
 */

beforeEach(function (): void {
    config()->set('coming-soon.enabled', false);

    // Seed the activity-counting flag the tracker queries against.
    Flag::query()->firstOrCreate(
        ['slug' => 'counts-toward-activity-requirements'],
        [
            'name' => 'Counts toward activity requirements',
            'family' => 'competition',
            'definition' => 'Attendance can be used toward dedicated-activity requirements.',
            'sort_order' => 100,
        ]
    );
});

it('reports hasDedicatedTracker true only when all three fields are set AND the user has Pro access', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
    ]);

    expect($user->hasDedicatedTracker())->toBeFalse();

    $user->forceFill([
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(6)->toDateString(),
        'dedicated_required_count' => 6,
    ])->save();

    expect($user->refresh()->hasDedicatedTracker())->toBeTrue();
});

it('does not render the tracker for a Free user even when the fields are set', function () {
    $user = User::factory()->create([
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(6)->toDateString(),
        'dedicated_required_count' => 6,
    ]); // Plan defaults to Free.

    expect($user->hasDedicatedTracker())->toBeFalse();
});

it('counts only activity-flagged events inside the renewal window', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(3)->toDateString(),
        'dedicated_required_count' => 6,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();

    // Four activity-flagged events inside the window — should count.
    $flaggedInWindow = Event::factory()->count(4)->create([
        'starts_at' => now()->subMonths(3),
    ]);
    foreach ($flaggedInWindow as $event) {
        $event->flags()->attach($flag->id);
        AttendedEvent::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'event_date' => now()->subMonths(3)->toDateString(),
        ]);
    }

    // One flagged event OUTSIDE the window (before window_start) —
    // must not count.
    $flaggedBefore = Event::factory()->create(['starts_at' => now()->subYears(2)]);
    $flaggedBefore->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $flaggedBefore->id,
        'event_date' => now()->subYears(2)->toDateString(),
    ]);

    // One unflagged event inside the window — must not count.
    $unflagged = Event::factory()->create(['starts_at' => now()->subMonths(2)]);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $unflagged->id,
        'event_date' => now()->subMonths(2)->toDateString(),
    ]);

    // One manual entry (no event_id) — never counts.
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => null,
        'event_date' => now()->subMonths(1)->toDateString(),
    ]);

    $progress = $user->dedicatedProgress();

    expect($progress['logged'])->toBe(4)
        ->and($progress['required'])->toBe(6)
        ->and($progress['remaining'])->toBe(2)
        ->and($progress['body'])->toBe('SAHGCA');
});

it('saves dedicated-status fields via the Livewire settings component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'SAHGCA')
        ->set('renewalOn', now()->addMonths(6)->toDateString())
        ->set('requiredCount', 6)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->dedicated_body)->toBe('SAHGCA')
        ->and($user->dedicated_required_count)->toBe(6)
        ->and($user->dedicated_renewal_on?->toDateString())->toBe(now()->addMonths(6)->toDateString());
});

it('rejects a required-count outside sensible bounds', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('requiredCount', 0)
        ->call('save')
        ->assertHasErrors(['requiredCount']);

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('requiredCount', 500)
        ->call('save')
        ->assertHasErrors(['requiredCount']);
});

it('clears every tracker field via the clear action', function () {
    $user = User::factory()->create([
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(6)->toDateString(),
        'dedicated_required_count' => 6,
    ]);

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->call('clear');

    $user->refresh();

    expect($user->dedicated_body)->toBeNull()
        ->and($user->dedicated_renewal_on)->toBeNull()
        ->and($user->dedicated_required_count)->toBeNull();
});

it('exports only activity-flagged entries in the renewal window', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(3)->toDateString(),
        'dedicated_required_count' => 6,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();

    $flagged = Event::factory()->create(['starts_at' => now()->subMonths(2), 'title' => 'Spring IPSC']);
    $flagged->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $flagged->id,
        'event_name_snapshot' => 'Spring IPSC',
        'event_date' => now()->subMonths(2)->toDateString(),
    ]);

    $unflagged = Event::factory()->create(['starts_at' => now()->subMonths(1), 'title' => 'Fun Shoot']);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $unflagged->id,
        'event_name_snapshot' => 'Fun Shoot',
        'event_date' => now()->subMonths(1)->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('my-log.export.dedicated'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Spring IPSC')
        ->and($csv)->not->toContain('Fun Shoot')
        ->and($csv)->toContain('SAHGCA');
});

it('blocks the dedicated export for users without the tracker set up', function () {
    $pro = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
    ]);

    $this->actingAs($pro)->get(route('my-log.export.dedicated'))->assertForbidden();
});

it('blocks the dedicated export for Free users even when fields are set', function () {
    $free = User::factory()->create([
        'dedicated_body' => 'SAHGCA',
        'dedicated_renewal_on' => now()->addMonths(6)->toDateString(),
        'dedicated_required_count' => 6,
    ]);

    $this->actingAs($free)->get(route('my-log.export.dedicated'))->assertForbidden();
});
