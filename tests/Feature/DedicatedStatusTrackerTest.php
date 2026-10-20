<?php

use App\Enums\Plan;
use App\Livewire\Settings\DedicatedStatus;
use App\Models\AttendedEvent;
use App\Models\Event;
use App\Models\Flag;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

/*
 * Dedicated Status Tracker: Pro-only renewal activity widget +
 * printable licence supporting document. The accrediting body is a
 * fixed set (NRAPA / NATSHOOT / FOSA) and the renewal cycle is
 * config-driven (default 1 Nov → 31 Oct), not a per-user date.
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

it('reports hasDedicatedTracker true as soon as a valid body is set AND the user has Pro', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
    ]);

    expect($user->hasDedicatedTracker())->toBeFalse();

    $user->forceFill(['dedicated_body' => 'nrapa'])->save();

    expect($user->refresh()->hasDedicatedTracker())->toBeTrue();
});

it('rejects an unknown body code even with Pro', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
    ]);

    $user->forceFill(['dedicated_body' => 'sahgca'])->save();

    expect($user->refresh()->hasDedicatedTracker())->toBeFalse();
});

it('does not render the tracker for a Free user even when the body is set', function () {
    $user = User::factory()->create([
        'dedicated_body' => 'nrapa',
    ]); // Plan defaults to Free.

    expect($user->hasDedicatedTracker())->toBeFalse();
});

it('counts only activity-flagged events inside the current cycle window', function () {
    // Freeze inside the 2026/27 cycle (1 Nov 2025 → 31 Oct 2026)
    // so cycle maths is deterministic regardless of when the suite runs.
    Carbon::setTestNow(Carbon::parse('2026-05-15'));

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();

    // Two activity-flagged events inside the cycle — should count.
    $flaggedInWindow = Event::factory()->count(2)->create();
    foreach ($flaggedInWindow as $event) {
        $event->flags()->attach($flag->id);
        AttendedEvent::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'event_date' => '2026-03-15',
        ]);
    }

    // One flagged event in the PREVIOUS cycle — must not count toward
    // "current".
    $flaggedBefore = Event::factory()->create();
    $flaggedBefore->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $flaggedBefore->id,
        'event_date' => '2025-06-15', // in cycle 2025/26, not 2026/27
    ]);

    // One unflagged event inside the current cycle — must not count.
    $unflagged = Event::factory()->create();
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $unflagged->id,
        'event_date' => '2026-04-01',
    ]);

    // One manual entry (no event_id) — never counts.
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => null,
        'event_date' => '2026-05-01',
    ]);

    $progress = $user->dedicatedProgress();

    expect($progress['logged'])->toBe(2)
        ->and($progress['required'])->toBe(2)
        ->and($progress['remaining'])->toBe(0)
        ->and($progress['body'])->toBe('nrapa')
        ->and($progress['body_label'])->toBe('NRAPA')
        ->and($progress['window_start']->toDateString())->toBe('2025-11-01')
        ->and($progress['window_end']->toDateString())->toBe('2026-10-31');

    Carbon::setTestNow();
});

it('exposes the previous cycle via dedicatedCycle(yearsBack)', function () {
    Carbon::setTestNow('2026-05-15');

    $user = User::factory()->create();

    $current = $user->dedicatedCycle(0);
    $previous = $user->dedicatedCycle(1);

    expect($current['start']->toDateString())->toBe('2025-11-01')
        ->and($current['end']->toDateString())->toBe('2026-10-31')
        ->and($previous['start']->toDateString())->toBe('2024-11-01')
        ->and($previous['end']->toDateString())->toBe('2025-10-31');

    Carbon::setTestNow();
});

it('rolls the current cycle forward once the end date passes', function () {
    Carbon::setTestNow('2026-11-01'); // Day after the 2026 end date.

    $user = User::factory()->create();
    $cycle = $user->dedicatedCycle();

    expect($cycle['start']->toDateString())->toBe('2026-11-01')
        ->and($cycle['end']->toDateString())->toBe('2027-10-31');

    Carbon::setTestNow();
});

it('saves dedicated-status fields via the Livewire settings component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'natshoot')
        ->set('membershipNumber', 'NAT-12345')
        ->set('statusNumber', 'DS-9876')
        ->set('category', 'sport_shooter')
        ->set('requiredCount', 3)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->dedicated_body)->toBe('natshoot')
        ->and($user->dedicated_membership_number)->toBe('NAT-12345')
        ->and($user->dedicated_status_number)->toBe('DS-9876')
        ->and($user->dedicated_category)->toBe('sport_shooter')
        ->and($user->dedicated_required_count)->toBe(3);
});

it('rejects a body that is not one of the configured codes', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'sahgca')
        ->call('save')
        ->assertHasErrors(['body']);
});

it('enforces the regulation floor of 2 activities per cycle', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'nrapa')
        ->set('requiredCount', 1)
        ->call('save')
        ->assertHasErrors(['requiredCount']);

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'nrapa')
        ->set('requiredCount', 500)
        ->call('save')
        ->assertHasErrors(['requiredCount']);
});

it('labels the required-count validation message with the field name shoppers see', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->set('body', 'nrapa')
        ->set('requiredCount', 1)
        ->call('save');

    expect($component->errors()->first('requiredCount'))
        ->toContain('activities required per cycle');
});

it('301-redirects the old /me/settings/notifications URL to the real settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/me/settings/notifications')
        ->assertStatus(301)
        ->assertRedirect('/me/settings');
});

it('defaults a new user to NRAPA with the regulation floor of 2', function () {
    $user = User::factory()->create(); // No dedicated_* fields set.

    $component = Livewire::actingAs($user)->test(DedicatedStatus::class);

    expect($component->get('body'))->toBe('nrapa')
        ->and($component->get('requiredCount'))->toBe(2);
});

it('clears every tracker field via the clear action', function () {
    $user = User::factory()->create([
        'dedicated_body' => 'nrapa',
        'dedicated_membership_number' => 'NR-1',
        'dedicated_status_number' => 'DS-2',
        'dedicated_category' => 'both',
        'dedicated_required_count' => 4,
    ]);

    Livewire::actingAs($user)
        ->test(DedicatedStatus::class)
        ->call('clear');

    $user->refresh();

    expect($user->dedicated_body)->toBeNull()
        ->and($user->dedicated_membership_number)->toBeNull()
        ->and($user->dedicated_status_number)->toBeNull()
        ->and($user->dedicated_category)->toBeNull()
        ->and($user->dedicated_required_count)->toBeNull();
});

it('exports only activity-flagged entries in the chosen cycle', function () {
    Carbon::setTestNow('2026-05-15');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();

    $flagged = Event::factory()->create(['title' => 'Spring IPSC']);
    $flagged->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $flagged->id,
        'event_name_snapshot' => 'Spring IPSC',
        'event_date' => '2026-03-01',
    ]);

    $unflagged = Event::factory()->create(['title' => 'Fun Shoot']);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $unflagged->id,
        'event_name_snapshot' => 'Fun Shoot',
        'event_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($user)->get(route('my-log.export.dedicated'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Spring IPSC')
        ->and($csv)->not->toContain('Fun Shoot')
        ->and($csv)->toContain('NRAPA');

    Carbon::setTestNow();
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
        'dedicated_body' => 'nrapa',
    ]);

    $this->actingAs($free)->get(route('my-log.export.dedicated'))->assertForbidden();
});
