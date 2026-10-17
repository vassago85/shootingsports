<?php

use App\Enums\EventLevel;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Rules\NotAProvinceName;
use App\Support\JsonLd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->withoutVite();
});

// --- B1: all-day display + schema ---------------------------------------

it('emits date-only startDate and endDate for all-day events in JSON-LD', function () {
    $event = Event::factory()->create([
        'title' => 'All-Day Shoot',
        'all_day' => true,
        // 10 Nov 2026 at 02:00 SAST (midnight-ish) — the "02:00 phantom"
        // the brief complains about. The schema must still read as the
        // date only, not "02:00".
        'starts_at' => Carbon::parse('2026-11-10 02:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-11-11 02:00', 'Africa/Johannesburg'),
    ]);

    $schema = JsonLd::event($event->fresh(['hostOrganisation.parent', 'venue', 'banner']));

    expect($schema['startDate'])->toBe('2026-11-10')
        ->and($schema['endDate'])->toBe('2026-11-11');
});

it('still emits ISO8601 datetimes for timed events in JSON-LD', function () {
    $event = Event::factory()->create([
        'title' => 'Timed Shoot',
        'all_day' => false,
        'starts_at' => Carbon::parse('2026-11-10 08:30', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-11-10 16:00', 'Africa/Johannesburg'),
    ]);

    $schema = JsonLd::event($event->fresh(['hostOrganisation.parent', 'venue', 'banner']));

    expect($schema['startDate'])->toContain('2026-11-10T08:30:00')
        ->and($schema['startDate'])->toEndWith('+02:00')
        ->and($schema['endDate'])->toContain('2026-11-10T16:00:00');
});

// --- B2: SAPRF importer heuristics --------------------------------------

it('infers ends_at from an N-Day SAPRF title and marks the row needs_review', function () {
    Discipline::factory()->create(['slug' => 'prs', 'name' => 'PRS']);

    Http::fake([
        'saprf.co.za/events*' => Http::response(<<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>24 Oct 2026</td>
                        <td><a href="https://saprf.co.za/events/201">Centrefire WC 2-Day National</a></td>
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

    $event = Event::query()->where('slug', 'saprf-event-201')->firstOrFail();

    expect($event->starts_at->toDateString())->toBe('2026-10-24')
        ->and($event->ends_at?->toDateString())->toBe('2026-10-25')
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('2-day');
});

it('leaves slash-separated SAPRF venues unlinked and flags them for review', function () {
    Discipline::factory()->create(['slug' => 'prs', 'name' => 'PRS']);

    Http::fake([
        'saprf.co.za/events*' => Http::response(<<<'HTML'
            <table>
                <tbody>
                    <tr>
                        <td>07 Nov 2026</td>
                        <td><a href="https://saprf.co.za/events/202">Nationals Weekend</a></td>
                        <td>PRS</td>
                        <td>national</td>
                        <td>WC</td>
                        <td>Day 1 - Papaberg / Day 2 - Legends</td>
                        <td>—</td>
                        <td>Open</td>
                    </tr>
                </tbody>
            </table>
            HTML, 200),
    ]);

    $this->artisan('calendar:import saprf')->assertSuccessful();

    $event = Event::query()->where('slug', 'saprf-event-202')->firstOrFail();

    expect($event->venue_id)->toBeNull()
        ->and($event->needs_review)->toBeTrue()
        ->and($event->import_notes)->toContain('Multi-venue');
});

// --- B5: town != province name ------------------------------------------

it('rejects a town that is just a province name', function () {
    $rule = new NotAProvinceName;

    foreach (['Gauteng', 'gauteng', 'Western Cape', 'western_cape', 'KwaZulu-Natal'] as $value) {
        $validator = Validator::make(['town' => $value], ['town' => $rule]);

        expect($validator->fails())->toBeTrue("value '{$value}' should be rejected");
    }
});

it('accepts a real town name', function () {
    $validator = Validator::make(['town' => 'Centurion'], ['town' => new NotAProvinceName]);

    expect($validator->passes())->toBeTrue();
});

// --- B8: inbound resources cannot create ---------------------------------

it('blocks staff from creating inbound records in the admin', function () {
    expect(ClaimResource::canCreate())->toBeFalse()
        ->and(SubmissionResource::canCreate())->toBeFalse()
        ->and(MediaResource::canCreate())->toBeFalse();
});

it('does not register a create page on inbound resources', function () {
    expect(ClaimResource::getPages())->not->toHaveKey('create')
        ->and(SubmissionResource::getPages())->not->toHaveKey('create')
        ->and(MediaResource::getPages())->not->toHaveKey('create');
});

// --- Dashboard tiles link to filtered lists -----------------------------

it('links the upcoming-matches tile to the same scope that produced the count', function () {
    $staff = User::factory()->staff()->create();

    // Match that satisfies the public upcoming scope.
    Event::factory()
        ->for(Organisation::factory()->state([
            'status' => ListingStatus::Published,
            'type' => OrganisationType::Club,
        ]), 'hostOrganisation')
        ->create([
            'title' => 'Future Public Shoot',
            'starts_at' => now()->addMonth(),
            'level' => EventLevel::Club,
        ]);

    // Match hidden from the public register (pending host).
    Event::factory()
        ->for(Organisation::factory()->state([
            'status' => ListingStatus::Pending,
            'type' => OrganisationType::Club,
        ]), 'hostOrganisation')
        ->create([
            'title' => 'Hidden Pending Shoot',
            'starts_at' => now()->addMonth(),
        ]);

    $url = EventResource::getUrl('index', [
        'filters' => ['on_public_calendar' => ['isActive' => true]],
    ]);

    $this->actingAs($staff)
        ->get($url)
        ->assertOk()
        ->assertSee('Future Public Shoot')
        ->assertDontSee('Hidden Pending Shoot');
});

it('links the ranges tile to the same scope that produced the count', function () {
    $staff = User::factory()->staff()->create();

    Venue::factory()->create([
        'name' => 'Public Range',
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
    ]);
    Venue::factory()->create([
        'name' => 'Hidden Range',
        'status' => ListingStatus::Pending,
        'province' => Province::Gauteng,
    ]);

    $url = VenueResource::getUrl('index', [
        'filters' => ['on_public_register' => ['isActive' => true]],
    ]);

    $this->actingAs($staff)
        ->get($url)
        ->assertOk()
        ->assertSee('Public Range')
        ->assertDontSee('Hidden Range');
});
