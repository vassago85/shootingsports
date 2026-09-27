<?php

use App\Enums\ListingStatus;
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

it('returns a venue that has hosted a published event in the discipline even without a pivot row', function () {
    $prs = Discipline::factory()->create([
        'slug' => 'precision-rifle-ranges',
        'name' => 'Precision Rifle',
        'is_published' => true,
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Lohatlha Long Range',
        'status' => ListingStatus::Published,
        'province' => Province::NorthernCape,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => Organisation::factory()->create()->id,
        'venue_id' => $venue->id,
    ]);
    $event->syncDisciplines([$prs->id], $prs->id);

    $ranges = $this->stats->rangesForDiscipline($prs);

    expect($ranges->pluck('id'))->toContain($venue->id);
});

it('scopes rangesForDiscipline to a province when supplied', function () {
    $prs = Discipline::factory()->create(['slug' => 'prs-ranges-province', 'is_published' => true]);
    $gpRange = Venue::factory()->create(['status' => ListingStatus::Published, 'province' => Province::Gauteng]);
    $wcRange = Venue::factory()->create(['status' => ListingStatus::Published, 'province' => Province::WesternCape]);
    $gpRange->disciplines()->syncWithoutDetaching([$prs->id]);
    $wcRange->disciplines()->syncWithoutDetaching([$prs->id]);

    $gpOnly = $this->stats->rangesForDiscipline($prs, Province::Gauteng);

    expect($gpOnly->pluck('id'))->toContain($gpRange->id)
        ->and($gpOnly->pluck('id'))->not->toContain($wcRange->id);
});

it('agrees with rangesCountForDiscipline', function () {
    $prs = Discipline::factory()->create(['slug' => 'prs-agree', 'is_published' => true]);

    Venue::factory()->count(3)->create(['status' => ListingStatus::Published])
        ->each(fn (Venue $v) => $v->disciplines()->syncWithoutDetaching([$prs->id]));

    expect($this->stats->rangesForDiscipline($prs)->count())
        ->toBe($this->stats->rangesCountForDiscipline($prs));
});
