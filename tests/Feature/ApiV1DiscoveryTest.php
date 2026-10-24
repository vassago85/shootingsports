<?php

use App\Enums\DisciplineFamily;
use App\Enums\FlagFamily;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Flag;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;

it('lists upcoming events as json for the mobile calendar', function (): void {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    Event::factory()->confirmed()->create([
        'title' => 'Highveld Steel',
        'venue_id' => $gauteng->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Boland Gong',
        'venue_id' => Venue::factory()->create(['province' => Province::WesternCape])->id,
    ]);

    $response = $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'title', 'starts_at', 'location_label', 'url', 'venue' => ['slug', 'lat', 'lng']]],
            'meta' => ['count'],
        ]);

    expect(collect($response->json('data'))->pluck('title'))
        ->toContain('Highveld Steel')
        ->toContain('Boland Gong');
});

it('honours the province filter on the events endpoint', function (): void {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    $cape = Venue::factory()->create(['province' => Province::WesternCape]);

    Event::factory()->confirmed()->create(['title' => 'Highveld Steel', 'venue_id' => $gauteng->id]);
    Event::factory()->confirmed()->create(['title' => 'Boland Gong', 'venue_id' => $cape->id]);

    $titles = collect($this->getJson('/api/v1/events?province=gauteng')->json('data'))->pluck('title');

    expect($titles)->toContain('Highveld Steel')->not->toContain('Boland Gong');
});

it('honours the venue slug filter so the mobile map can show a single range', function (): void {
    // Two Gauteng venues: tapping the pin for one should NOT bring up
    // the other's matches, even though they share a province. This is
    // the regression the user caught — the map was passing `?province=`
    // instead of `?venue=` and collapsing both back into one list.
    $thabazimbi = Venue::factory()->create([
        'slug' => 'thabazimbi-range',
        'province' => Province::Gauteng,
    ]);
    $wonderboom = Venue::factory()->create([
        'slug' => 'wonderboom-range',
        'province' => Province::Gauteng,
    ]);

    Event::factory()->confirmed()->create([
        'title' => 'Thabazimbi Steel',
        'venue_id' => $thabazimbi->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Wonderboom Precision',
        'venue_id' => $wonderboom->id,
    ]);

    $titles = collect(
        $this->getJson('/api/v1/events?venue=thabazimbi-range')->json('data'),
    )->pluck('title');

    expect($titles)
        ->toContain('Thabazimbi Steel')
        ->not->toContain('Wonderboom Precision');
});

it('returns event detail with description, venues, and match url', function (): void {
    $event = Event::factory()->confirmed()->create([
        'title' => 'National Precision',
        'description' => 'Two-day rifle match.',
    ]);

    $this->getJson('/api/v1/events/'.$event->slug)
        ->assertOk()
        ->assertJsonPath('data.title', 'National Precision')
        ->assertJsonPath('data.description', 'Two-day rifle match.')
        ->assertJsonStructure(['data' => ['description', 'venues', 'url']]);
});

it('does not expose draft matches through the API', function (): void {
    $draft = Event::factory()->draft()->create(['title' => 'Hidden Draft']);

    $this->getJson('/api/v1/events/'.$draft->slug)->assertNotFound();

    $titles = collect($this->getJson('/api/v1/events')->json('data'))->pluck('title');
    expect($titles)->not->toContain('Hidden Draft');
});

it('returns disciplines with upcoming counts', function (): void {
    Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $data = $this->getJson('/api/v1/disciplines')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'family']]])
        ->json('data');

    expect(collect($data)->pluck('slug'))->toContain('prs');
});

it('lists published venues for the Find Ranges mode', function (): void {
    $gauteng = Venue::factory()->create([
        'name' => 'Thabazimbi Range',
        'province' => Province::Gauteng,
        'town' => 'Thabazimbi',
    ]);
    $cape = Venue::factory()->create([
        'name' => 'Boland Range',
        'province' => Province::WesternCape,
        'town' => 'Paarl',
    ]);

    Event::factory()->confirmed()->create(['venue_id' => $gauteng->id]);
    Event::factory()->confirmed()->create(['venue_id' => $gauteng->id]);

    $response = $this->getJson('/api/v1/venues')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'slug',
                'name',
                'town',
                'province',
                'province_slug',
                'lat',
                'lng',
                'url',
                'logo_url',
                'summary',
                'disciplines',
                'upcoming_count',
            ]],
        ])
        ->json('data');

    $thabazimbi = collect($response)->firstWhere('name', 'Thabazimbi Range');
    expect($thabazimbi)->not->toBeNull()
        ->and($thabazimbi['upcoming_count'])->toBe(2)
        ->and($thabazimbi['town'])->toBe('Thabazimbi');

    $boland = collect($response)->firstWhere('name', 'Boland Range');
    expect($boland)->not->toBeNull()
        ->and($boland['upcoming_count'])->toBe(0);
});

it('honours the province filter on the venues endpoint', function (): void {
    Venue::factory()->create(['name' => 'Thabazimbi Range', 'province' => Province::Gauteng]);
    Venue::factory()->create(['name' => 'Boland Range', 'province' => Province::WesternCape]);

    $names = collect(
        $this->getJson('/api/v1/venues?province=gauteng')->json('data'),
    )->pluck('name');

    expect($names)->toContain('Thabazimbi Range')->not->toContain('Boland Range');
});

it('lists published clubs for the Find Clubs mode', function (): void {
    $gauteng = Organisation::factory()->create([
        'name' => 'Gauteng Rifle Club',
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Boland Rifle Club',
        'type' => OrganisationType::Club,
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'slug',
                'name',
                'short_name',
                'type',
                'town',
                'province',
                'province_slug',
                'logo_url',
                'url',
                'visitors_welcome',
                'disciplines',
            ]],
        ])
        ->json('data');

    $names = collect($response)->pluck('name');
    expect($names)->toContain('Gauteng Rifle Club', 'Boland Rifle Club');
});

it('includes federations and clubs in the default organisations list', function (): void {
    Organisation::factory()->create([
        'name' => 'SA Shooting Federation',
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Gauteng Rifle Club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);

    // Default scope now covers every membership-adjacent body so
    // the mobile Clubs tab can show a National group above the
    // province groups. The dedicated `?type=` override still works
    // when a screen needs to narrow further.
    $names = collect($this->getJson('/api/v1/organisations')->json('data'))
        ->pluck('name');

    expect($names)
        ->toContain('Gauteng Rifle Club')
        ->toContain('SA Shooting Federation');
});

it('honours the province filter on the organisations endpoint', function (): void {
    Organisation::factory()->create([
        'name' => 'Gauteng Rifle Club',
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Boland Rifle Club',
        'type' => OrganisationType::Club,
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $names = collect(
        $this->getJson('/api/v1/organisations?province=gauteng')->json('data'),
    )->pluck('name');

    expect($names)->toContain('Gauteng Rifle Club')->not->toContain('Boland Rifle Club');
});

it('lists published providers for the Find Shops mode', function (): void {
    Provider::factory()->create([
        'name' => 'Pretoria Gunsmith',
        'category' => ProviderCategory::Gunsmith,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Provider::factory()->create([
        'name' => 'Cape Optics',
        'category' => ProviderCategory::Optics,
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/providers')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'slug',
                'name',
                'category',
                'category_label',
                'town',
                'province',
                'province_slug',
                'tagline',
                'summary',
                'logo_url',
                'tier',
                'url',
            ]],
        ])
        ->json('data');

    $names = collect($response)->pluck('name');
    expect($names)->toContain('Pretoria Gunsmith', 'Cape Optics');
});

it('excludes distributor-only providers from the Shops list', function (): void {
    Provider::factory()->create([
        'name' => 'Trade Only Distributor',
        'category' => ProviderCategory::Distributor,
        'status' => ListingStatus::Published,
    ]);
    Provider::factory()->create([
        'name' => 'Pretoria Gunsmith',
        'category' => ProviderCategory::Gunsmith,
        'status' => ListingStatus::Published,
    ]);

    // Distributors are advertising accounts — they don't sell to the
    // public and have no business in a shopping surface.
    $names = collect($this->getJson('/api/v1/providers')->json('data'))->pluck('name');

    expect($names)
        ->toContain('Pretoria Gunsmith')
        ->not->toContain('Trade Only Distributor');
});

it('honours the category filter on the providers endpoint', function (): void {
    Provider::factory()->create([
        'name' => 'Pretoria Gunsmith',
        'category' => ProviderCategory::Gunsmith,
        'status' => ListingStatus::Published,
    ]);
    Provider::factory()->create([
        'name' => 'Cape Optics',
        'category' => ProviderCategory::Optics,
        'status' => ListingStatus::Published,
    ]);

    $names = collect(
        $this->getJson('/api/v1/providers?category=gunsmith')->json('data'),
    )->pluck('name');

    expect($names)->toContain('Pretoria Gunsmith')->not->toContain('Cape Optics');
});

it('does not expose unpublished venues through the venues API', function (): void {
    Venue::factory()->create([
        'name' => 'Pending Range',
        'status' => ListingStatus::Pending,
    ]);
    Venue::factory()->create([
        'name' => 'Live Range',
        'status' => ListingStatus::Published,
    ]);

    $names = collect($this->getJson('/api/v1/venues')->json('data'))->pluck('name');

    expect($names)->toContain('Live Range')->not->toContain('Pending Range');
});

it('returns map pins for pinned venues with upcoming matches', function (): void {
    $venue = Venue::factory()->create([
        'lat' => -26.2041,
        'lng' => 28.0473,
        'province' => Province::Gauteng,
    ]);

    Event::factory()->confirmed()->create(['venue_id' => $venue->id]);

    $this->getJson('/api/v1/map')
        ->assertOk()
        ->assertJsonStructure([
            'pins' => [['slug', 'lat', 'lng', 'count']],
            'centroids' => [['slug', 'label', 'lat', 'lng']],
            'total_matches',
        ]);
});

/*
 * --------------------------------------------------------------------
 * Phase 2 mobile filter tests — Calendar's chip rows depend on these
 * query params. Any backend change to the filter semantics should trip
 * one of these tests before it reaches a user's APK.
 * --------------------------------------------------------------------
 */

it('filters events by discipline family on the API', function (): void {
    $rifle = Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $pistol = Discipline::factory()->create([
        'name' => 'IPSC',
        'slug' => 'ipsc-practical',
        'family' => DisciplineFamily::Handgun,
        'is_published' => true,
        'parent_id' => null,
    ]);

    Event::factory()->confirmed()->create(['title' => 'Rifle Match'])
        ->disciplines()->attach($rifle->id);
    Event::factory()->confirmed()->create(['title' => 'Pistol Match'])
        ->disciplines()->attach($pistol->id);

    $titles = collect($this->getJson('/api/v1/events?family=rifle')->json('data'))->pluck('title');

    expect($titles)->toContain('Rifle Match')->not->toContain('Pistol Match');
});

it('filters events by discipline slug on the API', function (): void {
    $prs = Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $fclass = Discipline::factory()->create([
        'name' => 'F-Class',
        'slug' => 'f-class',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    Event::factory()->confirmed()->create(['title' => 'National Precision'])
        ->disciplines()->attach($prs->id);
    Event::factory()->confirmed()->create(['title' => 'Bisley F-Class'])
        ->disciplines()->attach($fclass->id);

    $titles = collect($this->getJson('/api/v1/events?discipline=prs')->json('data'))->pluck('title');

    expect($titles)->toContain('National Precision')->not->toContain('Bisley F-Class');
});

it('filters events by the novice flag on the API', function (): void {
    $flag = Flag::query()->create([
        'slug' => 'new-shooter-friendly',
        'name' => 'New shooter friendly',
        'definition' => 'A first-timer can shoot without being a burden on the squad.',
        'family' => FlagFamily::Access,
    ]);

    $noviceEvent = Event::factory()->confirmed()->create(['title' => 'Novice Welcome']);
    $noviceEvent->flags()->attach($flag);

    Event::factory()->confirmed()->create(['title' => 'Experienced Only']);

    $titles = collect($this->getJson('/api/v1/events?novice=1')->json('data'))->pluck('title');

    expect($titles)->toContain('Novice Welcome')->not->toContain('Experienced Only');
});

it('filters events by a from/to date range on the API', function (): void {
    Event::factory()->confirmed()->create([
        'title' => 'This Weekend',
        'starts_at' => now()->addDays(2),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Months Away',
        'starts_at' => now()->addMonths(3),
    ]);

    $from = now()->toDateString();
    $to = now()->addDays(7)->toDateString();

    $titles = collect($this->getJson("/api/v1/events?from={$from}&to={$to}")->json('data'))
        ->pluck('title');

    expect($titles)->toContain('This Weekend')->not->toContain('Months Away');
});

it('returns match detail with banner url and host organisation logo', function (): void {
    $organisation = Organisation::factory()->create([
        'logo_path' => 'organisation-logos/example.png',
    ]);

    $event = Event::factory()->confirmed()->create([
        'title' => 'Branded Match',
        'host_organisation_id' => $organisation->id,
    ]);

    $data = $this->getJson('/api/v1/events/'.$event->slug)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'banner_url',
                'host' => [
                    'name',
                    'organisation' => ['slug', 'name', 'short_name', 'logo_url'],
                ],
            ],
        ])
        ->json('data');

    expect($data['host']['organisation']['logo_url'])
        ->toBeString()
        ->toContain('example.png');
});
