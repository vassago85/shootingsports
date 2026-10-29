<?php

use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Support\HomeWindows;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    // Anchor the whole suite on a Wednesday so weekend() = Fri–Sun of
    // the same week and next30Days() comfortably spans both.
    Carbon::setTestNow(Carbon::create(2026, 10, 21, 10, 0, 0, 'Africa/Johannesburg')); // Wednesday

    $this->user = User::factory()->create([
        'home_province' => Province::Gauteng,
        'extra_provinces' => null,
    ]);
    $this->token = $this->user->createToken('test')->plainTextToken;
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function callFeed(): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->getJson('/api/v1/me/feed');
}

function createFollow(User $user, string $morph, int $id): Follow
{
    return Follow::create([
        'user_id' => $user->id,
        'followable_type' => $morph,
        'followable_id' => $id,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function matchOnWeekend(array $overrides = []): Event
{
    [$weekendFrom] = HomeWindows::weekend();

    return Event::factory()->confirmed()->create(array_merge([
        'starts_at' => $weekendFrom->copy()->addHours(10),
    ], $overrides));
}

it('returns empty buckets and has_follows=false when the user follows nothing', function (): void {
    $json = callFeed()->assertOk()->json();

    expect($json['data']['saved'])->toBe([])
        ->and($json['data']['this_weekend'])->toBe([])
        ->and($json['data']['next_30_days'])->toBe([])
        ->and($json['meta']['has_follows'])->toBeFalse()
        ->and($json['meta']['needs_province'])->toBeFalse();
});

it('includes matches hosted by a followed club in the weekend bucket', function (): void {
    // Pin short_name to null so the reasons pill falls back to the
    // full `name` and the asserted shape stays deterministic across
    // runs (the factory's `short_name` is `optional()` by default).
    $club = Organisation::factory()->create(['name' => 'Followed Club', 'short_name' => null]);
    createFollow($this->user, 'organisation', $club->id);

    $match = matchOnWeekend(['host_organisation_id' => $club->id]);

    $data = callFeed()->assertOk()->json('data.this_weekend');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($match->id)
        ->and($data[0]['reasons'])->toEqual([
            ['type' => 'club', 'name' => 'Followed Club', 'province' => $club->province?->getLabel()],
        ]);
});

it('club follows ignore the user province', function (): void {
    // User has home_province=Gauteng but the followed club runs a
    // match at a Western Cape venue. Club follows should still surface.
    $this->user->update(['home_province' => Province::Gauteng, 'extra_provinces' => null]);

    $club = Organisation::factory()->create(['province' => Province::WesternCape]);
    createFollow($this->user, 'organisation', $club->id);

    $venueWC = Venue::factory()->create(['province' => Province::WesternCape]);
    $match = matchOnWeekend(['host_organisation_id' => $club->id, 'venue_id' => $venueWC->id]);

    $data = callFeed()->assertOk()->json('data.this_weekend');

    expect($data)->toHaveCount(1)->and($data[0]['id'])->toBe($match->id);
});

it('discipline follows are scoped by home + extra provinces', function (): void {
    $this->user->update([
        'home_province' => Province::Gauteng,
        'extra_provinces' => ['western_cape'],
    ]);

    $discipline = Discipline::factory()->create(['name' => 'Precision Rifle']);
    createFollow($this->user, 'discipline', $discipline->id);

    // Match in Gauteng (home) → included
    $venueGP = Venue::factory()->create(['province' => Province::Gauteng]);
    $matchGP = matchOnWeekend(['venue_id' => $venueGP->id]);
    $matchGP->disciplines()->attach($discipline->id);

    // Match in Western Cape (extra) → included
    $venueWC = Venue::factory()->create(['province' => Province::WesternCape]);
    $matchWC = matchOnWeekend(['venue_id' => $venueWC->id]);
    $matchWC->disciplines()->attach($discipline->id);

    // Match in KZN (not in user provinces) → excluded
    $venueKZN = Venue::factory()->create(['province' => Province::KwaZuluNatal]);
    $matchKZN = matchOnWeekend(['venue_id' => $venueKZN->id]);
    $matchKZN->disciplines()->attach($discipline->id);

    $ids = collect(callFeed()->assertOk()->json('data.this_weekend'))->pluck('id');

    expect($ids)->toContain($matchGP->id)
        ->toContain($matchWC->id)
        ->not->toContain($matchKZN->id);
});

it('federation follows pick up matches hosted by that federation', function (): void {
    $federation = Organisation::factory()->create([
        'type' => OrganisationType::Federation,
        'name' => 'South African Precision Rifle Federation',
        // Pin the short name so the reasons pill assertion below
        // doesn't rely on the factory's optional random short_name.
        'short_name' => 'SAPRF',
    ]);
    createFollow($this->user, 'organisation', $federation->id);

    $match = matchOnWeekend(['host_organisation_id' => $federation->id]);

    $data = callFeed()->assertOk()->json('data.this_weekend');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($match->id)
        ->and($data[0]['reasons'][0]['type'])->toBe('club')
        ->and($data[0]['reasons'][0]['name'])->toBe('SAPRF');
});

it('deduplicates a match that qualifies via both club and discipline follows', function (): void {
    $club = Organisation::factory()->create(['name' => 'Dual Club', 'province' => Province::Gauteng]);
    $discipline = Discipline::factory()->create(['name' => 'Precision Rifle']);
    createFollow($this->user, 'organisation', $club->id);
    createFollow($this->user, 'discipline', $discipline->id);

    $venueGP = Venue::factory()->create(['province' => Province::Gauteng]);
    $match = matchOnWeekend([
        'host_organisation_id' => $club->id,
        'venue_id' => $venueGP->id,
    ]);
    $match->disciplines()->attach($discipline->id);

    $data = callFeed()->assertOk()->json('data.this_weekend');

    expect($data)->toHaveCount(1); // deduped
    expect($data[0]['reasons'])->toHaveCount(2);
    expect($data[0]['reasons'][0]['type'])->toBe('club');     // club first
    expect($data[0]['reasons'][1]['type'])->toBe('discipline');
});

it('excludes cancelled matches from the buckets but keeps them in saved', function (): void {
    $club = Organisation::factory()->create();
    createFollow($this->user, 'organisation', $club->id);

    $cancelled = matchOnWeekend([
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Cancelled,
    ]);

    // Also save it so we can confirm the saved bucket keeps it
    $this->user->savedEvents()->attach($cancelled->id);

    $json = callFeed()->assertOk()->json('data');

    expect($json['this_weekend'])->toBe([])
        ->and($json['next_30_days'])->toBe([])
        ->and(collect($json['saved'])->pluck('id'))->toContain($cancelled->id);

    // And the status is still visible so the UI can colour it
    $saved = collect($json['saved'])->firstWhere('id', $cancelled->id);
    expect($saved['status'])->toBe('cancelled');
});

it('excludes already-saved matches from the buckets', function (): void {
    $club = Organisation::factory()->create();
    createFollow($this->user, 'organisation', $club->id);

    $match = matchOnWeekend(['host_organisation_id' => $club->id]);
    $this->user->savedEvents()->attach($match->id);

    $json = callFeed()->assertOk()->json('data');

    expect($json['this_weekend'])->toBe([])
        ->and(collect($json['saved'])->pluck('id'))->toContain($match->id);
});

it('sets meta.needs_province when a discipline is followed but no province is set', function (): void {
    $this->user->update(['home_province' => null, 'extra_provinces' => null]);

    $discipline = Discipline::factory()->create();
    createFollow($this->user, 'discipline', $discipline->id);

    $venue = Venue::factory()->create(['province' => Province::Gauteng]);
    $match = matchOnWeekend(['venue_id' => $venue->id]);
    $match->disciplines()->attach($discipline->id);

    $json = callFeed()->assertOk()->json();

    expect($json['meta']['has_follows'])->toBeTrue()
        ->and($json['meta']['needs_province'])->toBeTrue()
        // discipline branch skipped — bucket must be empty
        ->and($json['data']['this_weekend'])->toBe([]);
});

it('does not set needs_province when only clubs are followed (no province required)', function (): void {
    $this->user->update(['home_province' => null, 'extra_provinces' => null]);

    $club = Organisation::factory()->create();
    createFollow($this->user, 'organisation', $club->id);

    $json = callFeed()->assertOk()->json();

    expect($json['meta']['has_follows'])->toBeTrue()
        ->and($json['meta']['needs_province'])->toBeFalse();
});

it('surfaces the changed flag on saved matches that were materially edited', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 10, 10, 0, 0, 'Africa/Johannesburg'));

    $match = Event::factory()->confirmed()->create([
        'starts_at' => Carbon::create(2026, 10, 25, 10, 0, 0, 'Africa/Johannesburg'),
    ]);
    $this->user->savedEvents()->attach($match->id);

    // Material edit after the save
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 10, 0, 0, 'Africa/Johannesburg'));
    $match->update(['starts_at' => $match->starts_at->copy()->addDay()]);

    // Advance to the test anchor (Wed Oct 21) for the feed call
    Carbon::setTestNow(Carbon::create(2026, 10, 21, 10, 0, 0, 'Africa/Johannesburg'));

    $saved = callFeed()->assertOk()->json('data.saved');

    expect($saved[0]['changed'])->toBeTrue()
        ->and($saved[0]['last_material_change_at'])->not->toBeNull();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/feed')->assertStatus(401);
});

/* ============================================================
 * Near-you bucket (home-range geo filter)
 * ========================================================= */

/**
 * Set up a home range + travel radius on the signed-in user. Returns
 * the venue so individual tests can position matches around it.
 */
function giveUserHomeRange(float $lat = -26.0, float $lng = 28.0, int $radiusKm = 100): Venue
{
    /** @var Venue $home */
    $home = Venue::factory()->create([
        'name' => 'Home Range',
        'lat' => $lat,
        'lng' => $lng,
    ]);
    test()->user->update(['home_venue_id' => $home->id, 'travel_radius_km' => $radiusKm]);

    return $home;
}

it('returns an empty near_you bucket + has_home_venue=false by default', function (): void {
    $json = callFeed()->assertOk()->json();

    expect($json['data']['near_you'])->toBe([])
        ->and($json['meta']['has_home_venue'])->toBeFalse();
});

it('surfaces matches within the travel radius of the home range', function (): void {
    giveUserHomeRange(-26.0, 28.0, 100);

    // ~10km east of the home point — well inside 100km.
    $closeVenue = Venue::factory()->create(['lat' => -26.0, 'lng' => 28.1]);
    $closeMatch = matchOnWeekend(['venue_id' => $closeVenue->id]);

    // ~500km away — well outside 100km.
    $farVenue = Venue::factory()->create(['lat' => -22.0, 'lng' => 28.0]);
    matchOnWeekend(['venue_id' => $farVenue->id]);

    $json = callFeed()->assertOk()->json();

    expect(collect($json['data']['near_you'])->pluck('id'))->toContain($closeMatch->id)
        ->and($json['meta']['has_home_venue'])->toBeTrue();

    $farIds = collect($json['data']['near_you'])->pluck('id');
    expect($farIds)->not->toContain(matchOnWeekend(['venue_id' => $farVenue->id])->id);
});

it('attaches a near_you reason with distance_km and the home-range name', function (): void {
    $home = giveUserHomeRange(-26.0, 28.0, 200);

    // ~44km due south-east (0.3° lat ≈ 33km + 0.3° lng ≈ 30km → ~44km).
    $venue = Venue::factory()->create(['lat' => -26.3, 'lng' => 28.3]);
    matchOnWeekend(['venue_id' => $venue->id]);

    $entries = callFeed()->assertOk()->json('data.near_you');
    expect($entries)->toHaveCount(1);

    $reason = $entries[0]['reasons'][0];
    expect($reason['type'])->toBe('near_you')
        ->and($reason['distance_km'])->toBeInt()->toBeGreaterThan(30)->toBeLessThan(60)
        ->and($reason['name'])->toContain("km from {$home->name}");
});

it('ignores venues without coordinates even if nominally in range', function (): void {
    giveUserHomeRange();

    $noCoords = Venue::factory()->create(['lat' => null, 'lng' => null]);
    matchOnWeekend(['venue_id' => $noCoords->id]);

    expect(callFeed()->assertOk()->json('data.near_you'))->toBe([]);
});

it('dedupes near_you against saved, this_weekend, and next_30_days', function (): void {
    $club = Organisation::factory()->create(['name' => 'My Club']);
    createFollow($this->user, 'organisation', $club->id);

    giveUserHomeRange(-26.0, 28.0, 500);

    $venue = Venue::factory()->create(['lat' => -26.1, 'lng' => 28.1]);
    // Same match qualifies via BOTH followed club AND near-you radius.
    $match = matchOnWeekend(['venue_id' => $venue->id, 'host_organisation_id' => $club->id]);

    $json = callFeed()->assertOk()->json();

    expect(collect($json['data']['this_weekend'])->pluck('id'))->toContain($match->id)
        ->and(collect($json['data']['near_you'])->pluck('id'))->not->toContain($match->id);
});

it('skips the near_you bucket when the picked home range has no coordinates', function (): void {
    /** @var Venue $home */
    $home = Venue::factory()->create(['lat' => null, 'lng' => null]);
    $this->user->update(['home_venue_id' => $home->id, 'travel_radius_km' => 100]);

    $venue = Venue::factory()->create(['lat' => -26.0, 'lng' => 28.0]);
    matchOnWeekend(['venue_id' => $venue->id]);

    $json = callFeed()->assertOk()->json();

    expect($json['data']['near_you'])->toBe([])
        // has_home_venue stays true — the shooter picked one, the
        // data gap is on our side (missing geocode), not theirs.
        ->and($json['meta']['has_home_venue'])->toBeTrue();
});
