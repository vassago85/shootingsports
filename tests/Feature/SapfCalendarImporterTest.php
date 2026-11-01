<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\Province;
use App\Imports\CalendarImporter;
use App\Imports\SapfCalendarImporter;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Freeze time before the first fixture date so every match is
    // in the future and the importer stamps them as Confirmed.
    Carbon::setTestNow('2026-01-15 08:00:00');

    Discipline::factory()->create(['slug' => 'target-pistol', 'name' => 'Target Pistol']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

$fixture = <<<'HTML'
<html>
<body>
<table>
<tr><th>EVENT</th><th>DATE</th><th>VENUE</th><th>Entry Form</th><th>Results</th></tr>
<tr>
    <td>Central Gauteng Provincial Open Championships</td>
    <td>19 to 21 March 2026</td>
    <td>SANDF Range, Eeufees Road, Pretoria</td>
    <td></td>
    <td>ISSF NPA/PPC</td>
</tr>
<tr>
    <td>SAPF Nationals 2026 OPEN CHAMPIONSHIPS</td>
    <td>27 April 2026 to 2 May 2026</td>
    <td>SANDF Range, Eeufees Road, Pretoria</td>
    <td></td>
    <td>ISSF NPA/PPC</td>
</tr>
<tr>
    <td>Kwa-Zulu Natal Provincial Championships</td>
    <td>8 to 11 July 2026</td>
    <td>POSTAL</td>
    <td></td>
    <td></td>
</tr>
<tr>
    <td>Western Province Provincial Championships</td>
    <td>22 to 24 October 2026</td>
    <td>Cape Town, Bellville Pistol Club</td>
    <td></td>
    <td></td>
</tr>
</table>

<h2>Results 2025</h2>
<table>
<tr><th>EVENT</th><th>Results</th></tr>
<tr><td>Central Gauteng Provincial Open Championships</td><td>NPA/PPC ISSF</td></tr>
</table>
</body>
</html>
HTML;

it('parses the SAPF home-page table into match records', function () use ($fixture) {
    $parsed = (new SapfCalendarImporter(app(CalendarImporter::class)))->parse($fixture);

    expect($parsed)->toHaveCount(4);

    // Row 1 — in-month day range, province from venue.
    expect($parsed[0]->title)->toBe('Central Gauteng Provincial Open Championships')
        ->and($parsed[0]->startsAt->toDateString())->toBe('2026-03-19')
        ->and($parsed[0]->endsAt?->toDateString())->toBe('2026-03-21')
        ->and($parsed[0]->level)->toBe(EventLevel::Provincial)
        ->and($parsed[0]->status)->toBe(EventStatus::Confirmed)
        ->and($parsed[0]->province)->toBe(Province::Gauteng)
        ->and($parsed[0]->venueName)->toBe('SANDF Range')
        ->and($parsed[0]->venueTown)->toBe('Pretoria')
        ->and($parsed[0]->disciplineSlug)->toBe('target-pistol')
        ->and($parsed[0]->needsReview)->toBeFalse();

    // Row 2 — cross-month range, title says "Nationals".
    expect($parsed[1]->startsAt->toDateString())->toBe('2026-04-27')
        ->and($parsed[1]->endsAt?->toDateString())->toBe('2026-05-02')
        ->and($parsed[1]->level)->toBe(EventLevel::National);

    // Row 3 — POSTAL (mail-in). Venue is null but province still
    // comes from the title ("Kwa-Zulu Natal Provincial").
    expect($parsed[2]->venueName)->toBeNull()
        ->and($parsed[2]->venueTown)->toBeNull()
        ->and($parsed[2]->province)->toBe(Province::KwaZuluNatal)
        ->and($parsed[2]->needsReview)->toBeFalse();

    // Row 4 — "Cape Town, Bellville Pistol Club" — the longer club
    // name is the venue, the city is the town, province is WC.
    expect($parsed[3]->venueName)->toBe('Bellville Pistol Club')
        ->and($parsed[3]->venueTown)->toBe('Cape Town')
        ->and($parsed[3]->province)->toBe(Province::WesternCape);
});

it('persists SAPF matches through the full import pipeline', function () use ($fixture) {
    Http::fake([
        'www.samarksman.co.za/*' => Http::response($fixture, 200),
    ]);

    $this->artisan('calendar:import sapf')->assertSuccessful();

    $host = Organisation::query()->where('slug', 'sapf')->first();
    expect($host)->not->toBeNull()
        ->and($host->name)->toBe('South African Pistol Federation')
        ->and($host->website_url)->toBe('https://www.samarksman.co.za');

    $event = Event::query()
        ->where('title', 'SAPF Nationals 2026 OPEN CHAMPIONSHIPS')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->level)->toBe(EventLevel::National)
        ->and($event->source)->toBe(ListingSource::Import)
        ->and($event->starts_at->toDateString())->toBe('2026-04-27')
        ->and($event->ends_at?->toDateString())->toBe('2026-05-02')
        ->and($event->venue?->province)->toBe(Province::Gauteng)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['target-pistol']);

    // Postal row has no venue and should not create a venue row.
    $postal = Event::query()->where('title', 'Kwa-Zulu Natal Provincial Championships')->first();
    expect($postal)->not->toBeNull()
        ->and($postal->venue_id)->toBeNull();
});

it('leaves a staff-authored event alone when the SAPF importer re-runs', function () use ($fixture) {
    Http::fake([
        'www.samarksman.co.za/*' => Http::response($fixture, 200),
    ]);

    $staff = Event::factory()->confirmed()->create([
        'slug' => 'sapf-staff-override',
        'title' => 'Staff edit of SAPF Nationals',
        'entry_url' => 'https://www.samarksman.co.za/#sapf-sapf-nationals-2026-open-championships-2026-04-27',
        'source' => ListingSource::Staff,
    ]);

    $this->artisan('calendar:import sapf')->assertSuccessful();

    $staff->refresh();
    expect($staff->source)->toBe(ListingSource::Staff)
        ->and($staff->title)->toBe('Staff edit of SAPF Nationals');
});
