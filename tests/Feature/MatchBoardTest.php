<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Livewire\CalendarFilter;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('groups CalendarFilter results into "This weekend" and month buckets', function () {
    // Wednesday 16 Sep 2026 — this weekend is Sat 19 / Sun 20.
    Carbon::setTestNow(Carbon::create(2026, 9, 16, 10, 0, 0, 'Africa/Johannesburg'));

    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);

    Event::factory()->confirmed()->create([
        'title' => 'Weekend Steel',
        'host_organisation_id' => $host->id,
        'starts_at' => Carbon::create(2026, 9, 19, 9, 0, 0, 'Africa/Johannesburg'),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Next Weekend Gong',
        'host_organisation_id' => $host->id,
        'starts_at' => Carbon::create(2026, 9, 26, 9, 0, 0, 'Africa/Johannesburg'),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'October Long Range',
        'host_organisation_id' => $host->id,
        'starts_at' => Carbon::create(2026, 10, 10, 9, 0, 0, 'Africa/Johannesburg'),
    ]);

    Livewire::test(CalendarFilter::class)
        ->assertSee('This weekend')
        ->assertSee('Next weekend')
        ->assertSee('October 2026')
        ->assertSee('Weekend Steel')
        ->assertSee('Next Weekend Gong')
        ->assertSee('October Long Range');

    Carbon::setTestNow();
});

it('match row renders classification tags (national, series, novice) as outlined chips', function () {
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);

    $event = Event::factory()->create([
        'title' => 'Provincial Champs',
        'host_organisation_id' => $host->id,
        'status' => EventStatus::Confirmed,
        'level' => EventLevel::National,
        'starts_at' => now()->addDays(5),
    ]);

    $html = view('components.match-row', ['event' => $event])->render();

    expect($html)
        ->toContain('class="mb-row')
        ->toContain('Provincial Champs')
        ->toContain('mb-tag is-national')
        ->toContain('mb-state is-confirmed')
        ->toContain('View match');
});

it('match row uses distinct state classes for open, planned, full, cancelled', function () {
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
    ]);

    $states = [
        [EventStatus::EntriesOpen, 'is-open', 'Entries open'],
        [EventStatus::Planned, 'is-planned', 'Provisional'],
        [EventStatus::Full, 'is-full', 'Full'],
        [EventStatus::Cancelled, 'is-cancelled', 'Cancelled'],
    ];

    foreach ($states as [$status, $class, $label]) {
        $event = Event::factory()->create([
            'title' => 'Test — '.$label,
            'host_organisation_id' => $host->id,
            'status' => $status,
            'starts_at' => now()->addDays(5),
        ]);

        $html = view('components.match-row', ['event' => $event])->render();

        expect($html)
            ->toContain($class)
            ->toContain($label);
    }
});

it('calendar page carries the dark match-board shell', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'starts_at' => now()->addDays(3),
    ]);

    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee('class="match-board', false)
        ->assertSee('class="mb-toolbar"', false)
        ->assertSee('class="view-toggle"', false);
});

it('More filters chip exposes advanced controls in the DOM (aria-expanded toggle)', function () {
    // Panel is progressive-disclosure — hidden until clicked — but all
    // fields must exist in the initial HTML so bookmarked URLs work and
    // aria-controls resolves.
    Livewire::test(CalendarFilter::class)
        ->assertSee('More filters')
        ->assertSee('aria-controls="mb-more-panel"', false)
        ->assertSee('Near town')
        ->assertSee('Within');
});
