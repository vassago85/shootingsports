<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Services\Discovery\DiscoveryStats;

beforeEach(function (): void {
    $this->withoutVite();
    $this->stats = app(DiscoveryStats::class);
});

// --- clubsForDiscipline ---------------------------------------------------

it('includes a club that hosts published events in the discipline even without the pivot', function () {
    $prs = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'is_published' => true,
    ]);

    // Club that hosts a PRS event but has no disciplinables row.
    $pprc = Organisation::factory()->create([
        'name' => 'Pretoria Precision Rifle Club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $pprc->id,
        'venue_id' => Venue::factory()->create(['province' => Province::Gauteng])->id,
    ]);
    $event->syncDisciplines([$prs->id], $prs->id);

    $clubs = $this->stats->clubsForDiscipline($prs);

    expect($clubs->pluck('id'))->toContain($pprc->id);
});

it('includes a club that has the discipline attached even without event history', function () {
    $prs = Discipline::factory()->create(['slug' => 'prs-x', 'is_published' => true]);

    $club = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $club->attachDiscipline($prs);

    $clubs = $this->stats->clubsForDiscipline($prs);

    expect($clubs->pluck('id'))->toContain($club->id);
});

it('scopes clubsForDiscipline to a province when supplied', function () {
    $prs = Discipline::factory()->create(['is_published' => true]);
    $gpClub = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
    ]);
    $gpClub->attachDiscipline($prs);
    $wcClub = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'province' => Province::WesternCape,
    ]);
    $wcClub->attachDiscipline($prs);

    $gpOnly = $this->stats->clubsForDiscipline($prs, Province::Gauteng);

    expect($gpOnly->pluck('id'))->toContain($gpClub->id)
        ->and($gpOnly->pluck('id'))->not->toContain($wcClub->id);
});

// --- activeProvincesForDiscipline ----------------------------------------

it('counts every province with a published event in the discipline, not just clubs that opted in', function () {
    $prs = Discipline::factory()->create(['is_published' => true]);

    foreach ([Province::Gauteng, Province::Mpumalanga, Province::WesternCape] as $province) {
        $venue = Venue::factory()->create(['province' => $province]);
        $host = Organisation::factory()->create([
            'type' => OrganisationType::Club,
            'status' => ListingStatus::Published,
            'province' => $province,
        ]);
        $event = Event::factory()->confirmed()->create([
            'host_organisation_id' => $host->id,
            'venue_id' => $venue->id,
        ]);
        $event->syncDisciplines([$prs->id], $prs->id);
    }

    expect($this->stats->activeProvincesForDiscipline($prs))->toBe(3);
});

it('counts a venue-less event via its host organisation province', function () {
    $prs = Discipline::factory()->create(['is_published' => true]);

    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'province' => Province::KwaZuluNatal,
    ]);
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => null,
    ]);
    $event->syncDisciplines([$prs->id], $prs->id);

    expect($this->stats->activeProvincesForDiscipline($prs))->toBe(1);
});

// --- inferredDisciplinesForOrganisation ----------------------------------

it('infers disciplines for an organisation from its event history', function () {
    $prs = Discipline::factory()->create(['name' => 'PRS', 'is_published' => true]);
    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create(['host_organisation_id' => $host->id]);
    $event->syncDisciplines([$prs->id], $prs->id);

    $disciplines = $this->stats->inferredDisciplinesForOrganisation($host);

    expect($disciplines->pluck('id'))->toContain($prs->id);
});

// --- commonRangesForOrganisation ----------------------------------------

it('returns ranges commonly used by a club ordered by event count', function () {
    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $legends = Venue::factory()->create(['name' => 'Legends', 'status' => ListingStatus::Published]);
    $papaberg = Venue::factory()->create(['name' => 'Papaberg', 'status' => ListingStatus::Published]);

    // Two events at Legends, one at Papaberg.
    Event::factory()->confirmed()->count(2)->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $legends->id,
    ]);
    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $papaberg->id,
    ]);

    $ranges = $this->stats->commonRangesForOrganisation($host);

    expect($ranges->pluck('id')->all())->toBe([$legends->id, $papaberg->id]);
});

// --- clubsUsingVenue -----------------------------------------------------

it('returns clubs that have hosted matches at a range', function () {
    $venue = Venue::factory()->create(['status' => ListingStatus::Published]);
    $clubA = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $clubB = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);

    Event::factory()->confirmed()->count(3)->create([
        'host_organisation_id' => $clubA->id,
        'venue_id' => $venue->id,
    ]);
    Event::factory()->confirmed()->create([
        'host_organisation_id' => $clubB->id,
        'venue_id' => $venue->id,
    ]);

    $clubs = $this->stats->clubsUsingVenue($venue);

    expect($clubs->pluck('id')->all())->toBe([$clubA->id, $clubB->id]);
});

// --- Discipline::upcomingCounts inflation fix ----------------------------

it('does not double-count parent tiles when an event tags both parent and child disciplines', function () {
    $parent = Discipline::factory()->create(['is_published' => true]);
    $child = Discipline::factory()->create([
        'parent_id' => $parent->id,
        'is_published' => true,
    ]);
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $event = Event::factory()->create([
        'host_organisation_id' => $host->id,
        'starts_at' => now()->addDays(7),
        'status' => EventStatus::Confirmed,
    ]);
    $event->syncDisciplines([$parent->id, $child->id], $parent->id);

    $counts = Discipline::upcomingCounts();

    // Old bug would give 2 to the parent; correct answer is 1.
    expect($counts[$parent->id])->toBe(1)
        ->and($counts[$child->id])->toBe(1);
});
