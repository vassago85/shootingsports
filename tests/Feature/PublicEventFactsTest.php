<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Imports\CalendarImporter;
use App\Imports\ImportedMatch;
use App\Models\Event;
use App\Models\Organisation;
use App\Support\JsonLd;
use App\Support\PublicEventFacts;
use App\Support\Seo;
use Illuminate\Support\Carbon;

it('publishes the general entry and the member price as separate facts', function () {
    $event = Event::factory()->confirmed()->create([
        'entry_fee_cents' => 130000,
        'member_fee_cents' => 110000,
        'description' => 'Two-day national.',
    ]);

    $facts = PublicEventFacts::for($event);
    $offers = JsonLd::event($event)['offers'];

    expect($facts->priceSentence())->toBe('Entry is R1 300. Member entry is R1 100.')
        ->and(Seo::forEvent($event)->description)->toContain('Entry is R1 300.')
        ->and(Seo::forEvent($event)->description)->toContain('Member entry is R1 100.')
        ->and($offers[0]['name'])->toBe('Entry')
        ->and($offers[0]['price'])->toBe('1300.00')
        ->and($offers[1]['name'])->toBe('Member entry')
        ->and($offers[1]['price'])->toBe('1100.00');
});

it('labels a member-only price and omits an unknown price', function () {
    $memberOnly = Event::factory()->confirmed()->create([
        'entry_fee_cents' => null,
        'member_fee_cents' => 30000,
    ]);
    $unknown = Event::factory()->confirmed()->create([
        'entry_fee_cents' => null,
        'member_fee_cents' => null,
    ]);

    expect(PublicEventFacts::for($memberOnly)->priceRows())->toBe([['Member entry', 'R300']])
        ->and(JsonLd::event($memberOnly)['offers']['name'])->toBe('Member entry')
        ->and(JsonLd::event($memberOnly)['offers']['price'])->toBe('300.00')
        ->and(PublicEventFacts::for($unknown)->priceSentence())->toBeNull()
        ->and(JsonLd::event($unknown)['offers'])->not->toHaveKey('price');
});

it('names a missing venue with the known province and does not invent a place', function () {
    $host = Organisation::factory()->create([
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'venue_id' => null,
        'host_organisation_id' => $host->id,
        'title' => 'PR22 national',
        'level' => EventLevel::Club,
    ]);

    $payload = JsonLd::event($event);

    expect(PublicEventFacts::for($event)->venueLine())->toBe('Venue to be confirmed — Gauteng')
        ->and($payload)->not->toHaveKey('location')
        ->and($payload['description'])->toContain('Venue to be confirmed — Gauteng');
});

it('omits scheduled status and offer availability while a date is provisional', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Planned,
        'entry_fee_cents' => 5000,
        'confirmed_at' => null,
    ]);

    $payload = JsonLd::event($event);

    expect($payload)->not->toHaveKey('eventStatus')
        ->and($payload['offers'])->not->toHaveKey('availability')
        ->and($payload['description'])->toContain('Planned, date provisional');
});

it('flags a description date that contradicts the listed start and leaves the text alone', function () {
    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
    ]);

    $match = new ImportedMatch(
        externalId: 'vektor-event-9872',
        title: 'Vektor club day',
        entryUrl: 'https://example.test/events/9872',
        startsAt: Carbon::parse('2026-12-05', 'Africa/Johannesburg'),
        endsAt: null,
        level: EventLevel::Club,
        status: EventStatus::Confirmed,
        province: Province::Gauteng,
        venueName: null,
        disciplineSlug: null,
        description: 'The notice says 2025-12-05.',
    );

    app(CalendarImporter::class)->persist($host, [$match]);

    $event = Event::query()->where('slug', 'vektor-event-9872')->first();

    expect($event)->not->toBeNull()
        ->and($event->description)->toBe('The notice says 2025-12-05.')
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('2025-12-05');
});

it('flags a club-level event whose title says national without changing the level', function () {
    $event = Event::factory()->confirmed()->create([
        'title' => 'PR22 2 Day National 2026',
        'level' => EventLevel::Club,
        'description' => 'Season 08/2027 is not a start date.',
    ]);

    expect(PublicEventFacts::reviewNotes($event))->toContain('level is Club')
        ->and(PublicEventFacts::for($event)->conflictingDescriptionDates())->toBe([])
        ->and($event->level)->toBe(EventLevel::Club);
});
