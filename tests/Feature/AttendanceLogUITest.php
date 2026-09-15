<?php

use App\Enums\Plan;
use App\Livewire\AddManualAttendance;
use App\Livewire\LogAttendance;
use App\Models\AttendedEvent;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('a guest hitting /my-log is redirected to the login page', function () {
    $this->get('/my-log')->assertRedirect(route('login'));
});

it('/my-log renders for a signed-in user with no entries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/my-log')
        ->assertOk()
        ->assertSee('Attendance record')
        ->assertSee('No matches logged');
});

it('the I shot this button logs an event via LogAttendance', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(LogAttendance::class, ['event' => $event])
        ->call('openForm')
        ->assertSet('open', true)
        ->set('division', 'Standard')
        ->set('placing', 3)
        ->set('field_size', 24)
        ->call('save')
        ->assertSet('logged', true)
        ->assertSet('open', false);

    $entry = AttendedEvent::query()->firstOrFail();
    expect($entry->user_id)->toBe($user->id)
        ->and($entry->event_id)->toBe($event->id)
        ->and($entry->division)->toBe('Standard')
        ->and($entry->placing)->toBe(3)
        ->and($entry->field_size)->toBe(24)
        ->and($entry->event_name_snapshot)->toBe($event->title)
        ->and($entry->event_date->toDateString())->toBe($event->starts_at->toDateString());
});

it('a Free user at 3/3 gets the upgrade prompt instead of the form', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);
    AttendedEvent::factory()->count(3)->create(['user_id' => $user->id]);

    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(LogAttendance::class, ['event' => $event])
        ->call('openForm')
        ->assertSet('open', false)
        ->assertDispatched('open-upgrade-prompt', trigger: 'attendance_log_limit');
});

it('unlog removes the entry and flips logged back to false', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $event = Event::factory()->create();

    $entry = AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
    ]);

    Livewire::actingAs($user)
        ->test(LogAttendance::class, ['event' => $event])
        ->assertSet('logged', true)
        ->call('unlog')
        ->assertSet('logged', false);

    expect(AttendedEvent::query()->find($entry->id))->toBeNull();
});

it('AddManualAttendance creates a manual entry with a null event_id', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    Livewire::actingAs($user)
        ->test(AddManualAttendance::class)
        ->call('openForm')
        ->set('event_name', 'Old club shoot')
        ->set('event_date', '2024-03-15')
        ->set('host', 'Some Club')
        ->set('venue', 'A field')
        ->set('division', 'Production')
        ->call('save')
        ->assertSet('open', false)
        ->assertDispatched('attendance-added');

    $entry = AttendedEvent::query()->firstOrFail();
    expect($entry->event_id)->toBeNull()
        ->and($entry->event_name_snapshot)->toBe('Old club shoot')
        ->and($entry->event_date->toDateString())->toBe('2024-03-15')
        ->and($entry->host_snapshot)->toBe('Some Club');
});

it('AddManualAttendance validates the required fields', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    Livewire::actingAs($user)
        ->test(AddManualAttendance::class)
        ->call('openForm')
        ->set('event_name', '')  // missing
        ->set('event_date', '')  // missing
        ->call('save')
        ->assertHasErrors(['event_name', 'event_date']);

    expect(AttendedEvent::count())->toBe(0);
});

it('deleting an entry from /my-log removes it', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $entry = AttendedEvent::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete('/my-log/'.$entry->id)
        ->assertRedirect();

    expect(AttendedEvent::query()->find($entry->id))->toBeNull();
});

it('a user cannot delete another users log entry', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $entry = AttendedEvent::factory()->create(['user_id' => $theirs->id]);

    $this->actingAs($mine)
        ->delete('/my-log/'.$entry->id)
        ->assertForbidden();

    expect(AttendedEvent::query()->find($entry->id))->not->toBeNull();
});

it('/my-log filters entries by year', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'Old Match From 2024',
        'event_date' => '2024-05-10',
    ]);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'This Year Match',
        'event_date' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($user)
        ->get('/my-log?year='.now()->year)
        ->assertOk()
        ->assertSee('This Year Match')
        ->assertDontSee('Old Match From 2024');

    $this->actingAs($user)
        ->get('/my-log?year=2024')
        ->assertOk()
        ->assertSee('Old Match From 2024')
        ->assertDontSee('This Year Match');
});
