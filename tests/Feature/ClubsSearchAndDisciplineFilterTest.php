<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;

beforeEach(function (): void {
    $this->withoutVite();
});

it('filters the club directory by a name search', function () {
    Organisation::factory()->create([
        'name' => 'Pretoria Precision Rifle Club',
        'slug' => 'pretoria-precision-rifle-club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Cape Metal Silhouette',
        'slug' => 'cape-metal-silhouette',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('clubs.index', ['q' => 'Precision']))
        ->assertOk()
        ->assertSee('Pretoria Precision Rifle Club')
        ->assertDontSee('Cape Metal Silhouette');
});

it('shows clubs that host events in the selected discipline even without a pivot row', function () {
    $prs = Discipline::factory()->create([
        'name' => 'Precision Rifle Series',
        'slug' => 'prs-clubs-filter',
        'is_published' => true,
    ]);
    $club = Organisation::factory()->create([
        'name' => 'Historyless Rifle Club',
        'slug' => 'historyless-rifle-club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    // Match linked to the discipline — pivot on the club is NOT set,
    // so the search must fall back to event history.
    $event = Event::factory()->create([
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
    ]);
    $event->syncDisciplines([$prs->id], $prs->id);

    $this->get(route('clubs.index', ['discipline' => $prs->slug]))
        ->assertOk()
        ->assertSee('Historyless Rifle Club');
});

it('offers clear-filters and explore-sports actions on the empty state', function () {
    $this->get(route('clubs.index', ['q' => 'zzz-no-match']))
        ->assertOk()
        ->assertSee('No clubs match these filters')
        ->assertSee('Clear filters')
        ->assertSee('Explore shooting sports');
});
