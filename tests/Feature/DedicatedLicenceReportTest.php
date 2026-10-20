<?php

use App\Enums\Plan;
use App\Models\AttendedEvent;
use App\Models\Event;
use App\Models\Flag;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    config()->set('coming-soon.enabled', false);
    $this->withoutVite();

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

it('renders the printable licence report with header, cycle, totals and matching rows', function () {
    Carbon::setTestNow('2026-05-15');

    $user = User::factory()->create([
        'name' => 'Sam Shooter',
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_membership_number' => 'NRAPA-1234',
        'dedicated_status_number' => 'DS-5678',
        'dedicated_category' => 'sport_shooter',
        'dedicated_required_count' => 2,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();
    $event = Event::factory()->create(['title' => 'Autumn IPSC']);
    $event->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'event_name_snapshot' => 'Autumn IPSC',
        'event_date' => '2026-03-20',
    ]);

    $response = $this->actingAs($user)->get(route('my-log.dedicated.print'));

    $response->assertOk()
        ->assertSee('Sam Shooter')
        ->assertSee('NRAPA')
        ->assertSee('NRAPA-1234')
        ->assertSee('DS-5678')
        ->assertSee('Sport shooter')
        ->assertSee('1 of 2 required activities')
        ->assertSee('Autumn IPSC')
        ->assertSee('1 November 2025') // cycle start
        ->assertSee('31 October 2026'); // cycle end

    Carbon::setTestNow();
});

it('blocks the printable report for Free users', function () {
    $free = User::factory()->create([
        'dedicated_body' => 'nrapa',
        'dedicated_membership_number' => 'X',
    ]);

    $this->actingAs($free)
        ->get(route('my-log.dedicated.print'))
        ->assertForbidden();
});

it('blocks the printable report for Pro users without the tracker set up', function () {
    $pro = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
    ]);

    $this->actingAs($pro)
        ->get(route('my-log.dedicated.print'))
        ->assertForbidden();
});

it('supports a custom date range via from/to query parameters', function () {
    Carbon::setTestNow('2026-05-15');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    $flag = Flag::query()->where('slug', 'counts-toward-activity-requirements')->firstOrFail();

    $inRange = Event::factory()->create(['title' => 'In Range']);
    $inRange->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $inRange->id,
        'event_name_snapshot' => 'In Range',
        'event_date' => '2024-06-01',
    ]);

    $outOfRange = Event::factory()->create(['title' => 'Out Of Range']);
    $outOfRange->flags()->attach($flag->id);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => $outOfRange->id,
        'event_name_snapshot' => 'Out Of Range',
        'event_date' => '2026-01-15',
    ]);

    $response = $this->actingAs($user)
        ->get(route('my-log.dedicated.print', ['from' => '2024-01-01', 'to' => '2024-12-31']));

    $response->assertOk()
        ->assertSee('In Range')
        ->assertDontSee('Out Of Range')
        ->assertSee('(custom range)');

    Carbon::setTestNow();
});
