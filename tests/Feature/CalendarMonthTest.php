<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
});

it('/calendar/month renders the month grid and three-way toggle', function () {
    $html = $this->get(route('calendar.month'))->assertOk()->getContent();

    expect($html)
        ->toContain('class="view-toggle"')
        ->toContain('href="'.route('calendar.month'))
        ->toContain('href="'.route('calendar'))
        ->toContain('href="'.route('map').'"')
        ->toContain('class="month-cal"')
        ->toContain('upcoming');
});

it('month grid shows upcoming matches in the visible month only', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create([
        'status' => ListingStatus::Published,
        'town' => 'Centurion',
    ]);

    $inMonth = Event::factory()->confirmed()->create([
        'title' => 'October Gong Shoot',
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-10-15 09:00:00'),
    ]);

    Event::factory()->confirmed()->create([
        'title' => 'Past Winter Match',
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2025-06-01 09:00:00'),
        'status' => EventStatus::Completed,
    ]);

    $html = $this->get(route('calendar.month', ['month' => '2026-10']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('October Gong Shoot')
        ->toContain(route('matches.show', $inMonth->slug))
        ->not->toContain('Past Winter Match');
});

it('list and map toggles include Month link', function () {
    expect($this->get(route('calendar'))->assertOk()->getContent())
        ->toContain('href="'.route('calendar.month'))
        ->and($this->get(route('map'))->assertOk()->getContent())
        ->toContain('href="'.route('calendar.month'));
});

it('places a multi-day match on every day it runs', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create(['status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'title' => 'CentreFire WC 2-Day National',
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-10-24 08:00:00'),
        'ends_at' => Carbon::parse('2026-10-25 17:00:00'),
    ]);

    $html = $this->get(route('calendar.month', ['month' => '2026-10']))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'CentreFire WC 2-Day National'))->toBe(4);

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee('Saturday 24 – Sunday 25 October 2026', false)
        ->assertSee('Sat 24 – Sun 25 Oct 2026', false);
});
