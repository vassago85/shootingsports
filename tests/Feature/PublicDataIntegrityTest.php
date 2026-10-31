<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Imports\CalendarImporter;
use App\Imports\ImportedMatch;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

it('keeps a multi-venue import string off the range directory and out of new venue rows', function () {
    config(['seo.indexable' => true]);

    $real = Venue::factory()->create([
        'name' => 'Legends Adventure Farm',
        'province' => Province::Gauteng,
        'town' => 'Rayton',
        'status' => ListingStatus::Published,
    ]);
    $fragment = Venue::factory()->create([
        'name' => 'Day 1 - Papaberg / Day 2 - Legends Adv Farm',
        'province' => Province::Gauteng,
        'town' => 'Rayton',
        'address' => 'Imported timetable',
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee($real->name)
        ->assertDontSee($fragment->name);

    $this->get(route('ranges.show', $fragment->slug))
        ->assertOk()
        ->assertSee('noindex,follow', false)
        ->assertSee('lists more than one place', false);

    $host = Organisation::factory()->create();

    app(CalendarImporter::class)->persist($host, [publicDataIntegrityMatch(
        'audit-fragment-1',
        'Day 1 - Papaberg / Day 2 - Legends Adv Farm',
        Province::Gauteng,
    )]);

    $event = Event::query()->where('slug', 'audit-fragment-1')->firstOrFail();

    expect($event->venue_id)->toBeNull()
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('Multi-venue')
        ->and(Venue::query()->where('name', 'Day 1 - Papaberg / Day 2 - Legends Adv Farm')->count())->toBe(1);
});

it('links a feed to the one existing range of that name instead of creating a second province', function () {
    $westernCape = Venue::factory()->create([
        'name' => 'Kraaifontein',
        'province' => Province::WesternCape,
        'town' => 'Kraaifontein',
        'lat' => -33.847,
        'lng' => 18.721,
        'status' => ListingStatus::Published,
    ]);

    $host = Organisation::factory()->create();

    app(CalendarImporter::class)->persist($host, [publicDataIntegrityMatch(
        'audit-kraai-1',
        'Kraaifontein',
        Province::Mpumalanga,
    )]);

    $event = Event::query()->where('slug', 'audit-kraai-1')->firstOrFail();

    expect(Venue::query()->whereRaw('LOWER(name) = ?', ['kraaifontein'])->count())->toBe(1)
        ->and($event->venue_id)->toBe($westernCape->id)
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('does not match');
});

it('does not create a third range when the same name already exists in two provinces', function () {
    Venue::factory()->create([
        'name' => 'Kraaifontein',
        'province' => Province::WesternCape,
        'town' => 'Kraaifontein',
        'lat' => -33.847,
        'lng' => 18.721,
    ]);
    $mpumalanga = Venue::factory()->create([
        'name' => 'Kraaifontein',
        'province' => Province::Mpumalanga,
        'town' => 'Kraaifontein',
        'lat' => -25.5,
        'lng' => 30.5,
    ]);

    $host = Organisation::factory()->create();

    app(CalendarImporter::class)->persist($host, [publicDataIntegrityMatch(
        'audit-kraai-2',
        'Kraaifontein',
        Province::Mpumalanga,
    )]);

    $event = Event::query()->where('slug', 'audit-kraai-2')->firstOrFail();

    expect(Venue::query()->whereRaw('LOWER(name) = ?', ['kraaifontein'])->count())->toBe(2)
        ->and($event->venue_id)->toBe($mpumalanga->id)
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('More than one range');
});

it('tells the map reader when an upcoming match has no linked range', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create([
        'name' => 'Pinned Audit Range',
        'lat' => -26.2,
        'lng' => 28.04,
        'status' => ListingStatus::Published,
    ]);

    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addWeek(),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Unlinked Audit Match',
        'host_organisation_id' => $host->id,
        'venue_id' => null,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('map'))
        ->assertOk()
        ->assertSee('1 upcoming match with a linked range', false)
        ->assertSee('1 upcoming match has no linked range', false)
        ->assertSee('Pinned Audit Range');
});

function publicDataIntegrityMatch(string $id, string $venueName, Province $province): ImportedMatch
{
    return new ImportedMatch(
        externalId: $id,
        title: 'Imported match '.$id,
        entryUrl: 'https://example.test/matches/'.$id,
        startsAt: Carbon::parse('2026-10-31', 'Africa/Johannesburg'),
        endsAt: Carbon::parse('2026-11-01', 'Africa/Johannesburg'),
        level: EventLevel::National,
        status: EventStatus::Confirmed,
        province: $province,
        venueName: $venueName,
        disciplineSlug: null,
    );
}
