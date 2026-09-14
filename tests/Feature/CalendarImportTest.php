<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Imports\CgpsaCalendarImporter;
use App\Imports\MpsaCalendarImporter;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Support\Facades\Http;

it('lists scrape and sell sources', function () {
    $this->artisan('calendar:import --list')
        ->assertSuccessful()
        ->expectsOutputToContain('saprf')
        ->expectsOutputToContain('cgpsa')
        ->expectsOutputToContain('import')
        ->expectsOutputToContain('sapsa')
        ->expectsOutputToContain('sahunters')
        ->expectsOutputToContain('nrlhunter')
        ->expectsOutputToContain('kaapjag')
        ->expectsOutputToContain('sell');
});

it('imports SAPRF table rows without touching staff matches', function () {
    Discipline::factory()->create(['slug' => 'pr22-rimfire', 'name' => 'PR22']);
    Discipline::factory()->create(['slug' => 'prs', 'name' => 'PRS']);

    Event::factory()->confirmed()->create([
        'slug' => 'leave-me',
        'title' => 'Staff Match',
        'entry_url' => 'https://saprf.co.za/events/111',
        'source' => ListingSource::Staff,
    ]);

    Http::fake([
        'saprf.co.za/events*' => Http::response(<<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>17 Oct 2026</td>
                        <td><a href="https://saprf.co.za/events/111">Rimfire PR22 MP Provincial</a></td>
                        <td>PR22</td>
                        <td>provincial</td>
                        <td>MP</td>
                        <td>Balmoral Farm</td>
                        <td>—</td>
                        <td>Open</td>
                    </tr>
                    <tr>
                        <td>24 Oct 2026</td>
                        <td><a href="https://saprf.co.za/events/104">Centrefire WC 2-Day National</a></td>
                        <td>PRS</td>
                        <td>national</td>
                        <td>WC</td>
                        <td>Darling Steel Valley</td>
                        <td>—</td>
                        <td>Open</td>
                    </tr>
                </tbody>
            </table>
            HTML, 200),
    ]);

    $this->artisan('calendar:import saprf')->assertSuccessful();

    expect(Event::query()->where('title', 'Staff Match')->value('source'))->toBe(ListingSource::Staff)
        ->and(Event::query()->where('title', 'Staff Match')->value('entry_url'))->toBe('https://saprf.co.za/events/111')
        ->and(Event::query()->where('slug', 'saprf-event-104')->first())
        ->title->toBe('Centrefire WC 2-Day National')
        ->level->toBe(EventLevel::National)
        ->status->toBe(EventStatus::EntriesOpen)
        ->entry_url->toBe('https://saprf.co.za/events/104');

    expect(Organisation::query()->where('slug', 'saprf')->first())
        ->type->toBe(OrganisationType::Federation)
        ->status->toBe(ListingStatus::Published);
});

it('updates an existing SAPRF listing by entry URL instead of creating a second row', function () {
    Discipline::factory()->create(['slug' => 'pr22-rimfire', 'name' => 'PR22']);

    $existing = Event::factory()->confirmed()->create([
        'slug' => 'saprf-pr22-mp-provincial-2026',
        'title' => 'Rimfire PR22 MP Provincial',
        'entry_url' => 'https://saprf.co.za/events/111',
        'source' => ListingSource::Import,
    ]);

    Http::fake([
        'saprf.co.za/events*' => Http::response(<<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>17 Oct 2026</td>
                        <td><a href="https://saprf.co.za/events/111">Rimfire PR22 MP Provincial</a></td>
                        <td>PR22</td>
                        <td>provincial</td>
                        <td>MP</td>
                        <td>Balmoral Farm</td>
                        <td>—</td>
                        <td>Open</td>
                    </tr>
                </tbody>
            </table>
            HTML, 200),
    ]);

    $this->artisan('calendar:import saprf')->assertSuccessful();

    expect(Event::query()->where('entry_url', 'https://saprf.co.za/events/111')->count())->toBe(1)
        ->and($existing->fresh()->slug)->toBe('saprf-pr22-mp-provincial-2026')
        ->and($existing->fresh()->status)->toBe(EventStatus::EntriesOpen);
});

it('parses MPSA season dates that wrap a month', function () {
    Discipline::factory()->create(['slug' => 'ipsc-practical', 'name' => 'IPSC']);

    $matches = app(MpsaCalendarImporter::class)->parse(<<<'HTML'
        <table>
            <tr><th>Date</th><th>Venue</th><th>Description</th><th>Level</th></tr>
            <tr><td>2026-01-31/01</td><td>Teks</td><td>MPSA Handgun/PCC League #1</td><td>II</td></tr>
            <tr><td>2026-06-06</td><td>White River</td><td>MPSA Handgun/PCC League #4</td><td>II</td></tr>
        </table>
        HTML);

    expect($matches)->toHaveCount(2)
        ->and($matches[0]->startsAt->toDateString())->toBe('2026-01-31')
        ->and($matches[0]->endsAt?->toDateString())->toBe('2026-02-01')
        ->and($matches[0]->level)->toBe(EventLevel::Series)
        ->and($matches[1]->endsAt)->toBeNull()
        ->and($matches[1]->startsAt->toDateString())->toBe('2026-06-06');
});

it('imports CGPSA Events Calendar JSON without inventing fees', function () {
    Discipline::factory()->create(['slug' => 'ipsc-practical', 'name' => 'IPSC']);
    Discipline::factory()->create(['slug' => 'steel-challenge', 'name' => 'Steel Challenge']);

    Http::fake([
        'cgpsa.co.za/wp-json/tribe/events/v1/events*' => Http::response([
            'events' => [
                [
                    'id' => 2353,
                    'title' => 'CGPSA League 9',
                    'url' => 'https://cgpsa.co.za/events/cgpsa-league-9-3/',
                    'start_date' => '2026-10-03 00:00:00',
                    'end_date' => '2026-10-04 23:59:59',
                    'description' => '',
                    'categories' => [['slug' => 'league', 'name' => 'League Shoot']],
                    'venue' => [
                        'venue' => 'Golden City Shooting Club',
                        'city' => 'Alberton',
                        'province' => 'Gauteng',
                    ],
                ],
                [
                    'id' => 2372,
                    'title' => 'Steel Challenge',
                    'url' => 'https://cgpsa.co.za/events/steel-challenge-13/',
                    'start_date' => '2026-11-01 00:00:00',
                    'end_date' => '2026-11-01 23:59:59',
                    'description' => '',
                    'categories' => [],
                    'venue' => [
                        'venue' => 'Golden City Shooting Club',
                        'city' => 'Alberton',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('calendar:import cgpsa')->assertSuccessful();

    $league = Event::query()->where('slug', 'cgpsa-event-2353')->first();

    expect($league)->not->toBeNull()
        ->and($league->title)->toBe('CGPSA League 9')
        ->and($league->starts_at->toDateString())->toBe('2026-10-03')
        ->and($league->ends_at?->toDateString())->toBe('2026-10-04')
        ->and($league->level)->toBe(EventLevel::Series)
        ->and($league->status)->toBe(EventStatus::Confirmed)
        ->and($league->entry_fee_cents)->toBeNull()
        ->and($league->entry_url)->toBe('https://cgpsa.co.za/events/cgpsa-league-9-3/')
        ->and($league->venue?->name)->toBe('Golden City Shooting Club')
        ->and($league->venue?->town)->toBe('Alberton')
        ->and($league->venue?->province)->toBe(Province::Gauteng)
        ->and($league->disciplines->pluck('slug')->all())->toBe(['ipsc-practical']);

    $steel = Event::query()->where('slug', 'cgpsa-event-2372')->first();

    expect($steel)->not->toBeNull()
        ->and($steel->level)->toBe(EventLevel::Club)
        ->and($steel->disciplines->pluck('slug')->all())->toBe(['steel-challenge']);

    expect(Organisation::query()->where('slug', 'cgpsa')->first())
        ->type->toBe(OrganisationType::ProvincialBody)
        ->name->toBe('Central Gauteng Practical Shooting Association');
});

it('parses a CGPSA shotgun title onto sporting clays', function () {
    $matches = app(CgpsaCalendarImporter::class)->parse([
        'events' => [
            [
                'id' => 1,
                'title' => 'Shotgun Club Shoot',
                'url' => 'https://cgpsa.co.za/events/shotgun-club-shoot/',
                'start_date' => '2026-09-12 00:00:00',
                'end_date' => '2026-09-12 23:59:59',
                'venue' => ['venue' => 'Golden City Shooting Club', 'city' => 'Alberton'],
            ],
        ],
    ]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->disciplineSlug)->toBe('sporting-clays')
        ->and($matches[0]->level)->toBe(EventLevel::Club);
});

it('tells staff to sell the embed when a source has no feed', function () {
    $this->artisan('calendar:import sapsa')
        ->assertSuccessful()
        ->expectsOutputToContain('Sell them the calendar embed');
});
