<?php

use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

function callSuggestions(array $query = []): TestResponse
{
    $qs = http_build_query($query);

    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->getJson('/api/v1/me/follow-suggestions'.($qs !== '' ? '?'.$qs : ''));
}

/**
 * @param  array<int, int>  $disciplineIds
 */
function hostMatchesAt(Organisation $host, Venue $venue, array $disciplineIds, int $count): void
{
    foreach (range(1, $count) as $i) {
        $event = Event::factory()->confirmed()->create([
            'host_organisation_id' => $host->id,
            'venue_id' => $venue->id,
            'starts_at' => now()->addDays($i),
        ]);
        if ($disciplineIds !== []) {
            $event->disciplines()->attach($disciplineIds);
        }
    }
}

it('ranks clubs by upcoming match count within the given disciplines and province', function (): void {
    $prs = Discipline::factory()->create(['name' => 'Precision Rifle']);
    $gpVenue = Venue::factory()->create(['province' => Province::Gauteng]);

    $busyClub = Organisation::factory()->create(['name' => 'Busy GP Club']);
    $quietClub = Organisation::factory()->create(['name' => 'Quiet GP Club']);
    $wrongDisciplineClub = Organisation::factory()->create(['name' => 'Wrong Discipline']);
    $wrongProvinceClub = Organisation::factory()->create(['name' => 'KZN Club']);

    hostMatchesAt($busyClub, $gpVenue, [$prs->id], 3);
    hostMatchesAt($quietClub, $gpVenue, [$prs->id], 1);
    hostMatchesAt($wrongDisciplineClub, $gpVenue, [], 2); // no disciplines → doesn't match whereHas
    $kznVenue = Venue::factory()->create(['province' => Province::KwaZuluNatal]);
    hostMatchesAt($wrongProvinceClub, $kznVenue, [$prs->id], 2);

    $data = callSuggestions(['province' => 'gauteng', 'disciplines' => [$prs->id]])
        ->assertOk()
        ->json('data.regional');

    $names = collect($data)->pluck('name');

    expect($names)->toContain('Busy GP Club')
        ->toContain('Quiet GP Club')
        ->not->toContain('Wrong Discipline')
        ->not->toContain('KZN Club');

    // Ordered by match count desc
    expect($data[0]['name'])->toBe('Busy GP Club')
        ->and($data[0]['upcoming_count'])->toBe(3)
        ->and($data[1]['name'])->toBe('Quiet GP Club')
        ->and($data[1]['upcoming_count'])->toBe(1);
});

it('groups federations and nationwide associations into the national bucket', function (): void {
    $prs = Discipline::factory()->create();
    $gpVenue = Venue::factory()->create(['province' => Province::Gauteng]);

    $federation = Organisation::factory()->create([
        'name' => 'SAPRF',
        'type' => OrganisationType::Federation,
    ]);
    $nationwideAssoc = Organisation::factory()->create([
        'name' => 'SA Hunters',
        'type' => OrganisationType::Association,
        'province' => null, // provinceless → counts as national
    ]);
    $regularClub = Organisation::factory()->create([
        'name' => 'Local Club',
        'type' => OrganisationType::Club,
    ]);

    hostMatchesAt($federation, $gpVenue, [$prs->id], 2);
    hostMatchesAt($nationwideAssoc, $gpVenue, [$prs->id], 1);
    hostMatchesAt($regularClub, $gpVenue, [$prs->id], 3);

    $json = callSuggestions(['province' => 'gauteng', 'disciplines' => [$prs->id]])
        ->assertOk()
        ->json('data');

    $nationalNames = collect($json['national'])->pluck('name');
    $regionalNames = collect($json['regional'])->pluck('name');

    expect($nationalNames)->toContain('SAPRF')->toContain('SA Hunters')
        ->and($regionalNames)->toContain('Local Club');

    // Nationals ranked by count too
    expect($json['national'][0]['name'])->toBe('SAPRF');
});

it('accepts discipline slugs instead of ids', function (): void {
    $prs = Discipline::factory()->create(['slug' => 'precision-rifle']);
    $gpVenue = Venue::factory()->create(['province' => Province::Gauteng]);
    $club = Organisation::factory()->create();

    hostMatchesAt($club, $gpVenue, [$prs->id], 1);

    $names = collect(
        callSuggestions(['province' => 'gauteng', 'disciplines' => ['precision-rifle']])
            ->assertOk()
            ->json('data.regional')
    )->pluck('name');

    expect($names)->toContain($club->name);
});

it('accepts the kebab-case province slug variant', function (): void {
    $prs = Discipline::factory()->create();
    $kznVenue = Venue::factory()->create(['province' => Province::KwaZuluNatal]);
    $club = Organisation::factory()->create();

    hostMatchesAt($club, $kznVenue, [$prs->id], 1);

    $data = callSuggestions(['province' => 'kwazulu-natal', 'disciplines' => [$prs->id]])
        ->assertOk()
        ->json('data.regional');

    expect($data)->toHaveCount(1);
});

it('returns empty buckets when nothing matches', function (): void {
    $prs = Discipline::factory()->create();

    $json = callSuggestions(['province' => 'gauteng', 'disciplines' => [$prs->id]])
        ->assertOk()
        ->json('data');

    expect($json)->toBe(['national' => [], 'regional' => []]);
});

it('ignores cancelled matches when counting', function (): void {
    $prs = Discipline::factory()->create();
    $gpVenue = Venue::factory()->create(['province' => Province::Gauteng]);
    $club = Organisation::factory()->create();

    hostMatchesAt($club, $gpVenue, [$prs->id], 1); // 1 live

    // Add a cancelled one that should be ignored
    $cancelled = Event::factory()->cancelled()->create([
        'host_organisation_id' => $club->id,
        'venue_id' => $gpVenue->id,
        'starts_at' => now()->addDays(7),
    ]);
    $cancelled->disciplines()->attach($prs->id);

    $data = callSuggestions(['province' => 'gauteng', 'disciplines' => [$prs->id]])
        ->assertOk()
        ->json('data.regional');

    expect($data)->toHaveCount(1)->and($data[0]['upcoming_count'])->toBe(1);
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/follow-suggestions')->assertStatus(401);
});
