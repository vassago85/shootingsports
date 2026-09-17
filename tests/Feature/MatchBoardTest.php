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

it('match row shows the host organisation logo when one is set', function () {
    $host = Organisation::factory()->create([
        'name' => 'South African Precision Rifle Federation',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Federation,
        'logo_path' => 'organisation-logos/saprf.png',
    ]);

    $event = Event::factory()->create([
        'title' => 'Centrefire Open',
        'host_organisation_id' => $host->id,
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addDays(5),
    ]);
    $event->load('hostOrganisation');

    $html = view('components.match-row', ['event' => $event])->render();

    expect($html)
        ->toContain('class="mb-logo"')
        ->toContain('/media/organisation-logos/saprf.png')
        ->toContain('alt="South African Precision Rifle Federation"');
});

it('match row falls back to the parent federation logo when the club has none', function () {
    $federation = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Federation,
        'logo_path' => 'organisation-logos/parent-fed.png',
    ]);
    $club = Organisation::factory()->create([
        'name' => 'Local Rifle Club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'parent_id' => $federation->id,
        'logo_path' => null,
    ]);

    $event = Event::factory()->create([
        'title' => 'Club Day',
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addDays(5),
    ]);
    $event->load(['hostOrganisation.parent']);

    $html = view('components.match-row', ['event' => $event])->render();

    expect($html)
        ->toContain('class="mb-logo"')
        ->toContain('/media/organisation-logos/parent-fed.png');
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

it('shows dismissible chips for every active filter including provinces behind More filters', function () {
    Livewire::test(CalendarFilter::class)
        ->set('family', 'rifle')
        ->set('province', 'gauteng')
        ->set('confirmed', true)
        ->assertSee('Filtered:')
        ->assertSee('Clear all')
        ->assertSee('wire:click="clearFamily"', false)
        ->assertSee('wire:click="clearConfirmed"', false)
        ->assertSee("wire:click=\"toggleProvince('gauteng')\"", false)
        ->call('clearFamily')
        ->assertSet('family', 'all')
        ->call('clearAll')
        ->assertSet('province', null)
        ->assertSet('confirmed', false)
        ->assertDontSee('Filtered:');
});

it('stacks family, weekend and province filters without clearing earlier ones', function () {
    Livewire::test(CalendarFilter::class)
        ->call('setFamily', 'rifle')
        ->assertSet('family', 'rifle')
        ->call('toggleWeekend')
        ->assertSet('weekend', true)
        ->assertSet('family', 'rifle')
        ->call('toggleProvince', 'gauteng')
        ->assertSet('province', 'gauteng')
        ->assertSet('family', 'rifle')
        ->assertSet('weekend', true)
        ->call('toggleNovice')
        ->assertSet('novice', true)
        ->assertSet('family', 'rifle')
        ->assertSet('weekend', true)
        ->assertSet('province', 'gauteng');
});
