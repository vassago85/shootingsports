<?php

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;

it('downloads a public calendar file with a stable uid and an exclusive all-day end', function () {
    $event = Event::factory()->confirmed()->create([
        'title' => 'Two day club match',
        'all_day' => true,
        'starts_at' => Carbon::parse('2026-12-05', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-12-06', 'Africa/Johannesburg'),
        'entry_fee_cents' => 130000,
        'member_fee_cents' => 110000,
    ]);

    $body = $this->get(route('ical.event', $event))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8')
        ->getContent();

    $unfolded = str_replace("\r\n ", '', $body);

    expect($body)->toContain('BEGIN:VCALENDAR')
        ->and($body)->toContain('UID:event-'.$event->id.'@shootingsports.co.za')
        ->and($body)->toMatch('/DTSTAMP:\d{8}T\d{6}Z/')
        ->and($body)->toContain('DTSTART;VALUE=DATE:20261205')
        ->and($body)->toContain('DTEND;VALUE=DATE:20261207')
        ->and($unfolded)->toContain('Member entry is R1 100');
});

it('emits timed events in utc and folds long lines', function () {
    $event = Event::factory()->confirmed()->create([
        'title' => str_repeat('Long match title ', 8),
        'all_day' => false,
        'starts_at' => Carbon::parse('2026-12-05 09:00:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-12-05 15:00:00', 'Africa/Johannesburg'),
    ]);

    $body = $this->get(route('ical.event', $event))->assertOk()->getContent();

    expect($body)->toContain('DTSTART:20261205T070000Z')
        ->and($body)->toContain('DTEND:20261205T130000Z')
        ->and($body)->toContain("\r\n ");
});

it('returns 404 for a draft event calendar file', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Draft,
    ]);

    $this->get(route('ical.event', $event))->assertNotFound();
});
