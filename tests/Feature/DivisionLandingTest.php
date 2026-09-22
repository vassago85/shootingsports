<?php

use App\Enums\AdPage;
use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Enums\OrganisationType;
use App\Enums\PlacementSlot;
use App\Models\AdSlot;
use App\Models\Discipline;
use App\Models\DisciplineVideo;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Placement;
use App\Models\Provider;
use App\Support\AdPlacements;
use App\Support\YouTube;

beforeEach(function () {
    $this->withoutVite();
});

it('asks which division on the home page and links the month calendar', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('What are you interested in?')
        ->toContain('Find your')
        ->toContain('href="'.route('divisions.show', 'handgun').'"')
        ->toContain('href="'.route('divisions.show', 'bolt-action-rifle').'"')
        ->toContain('href="'.route('divisions.show', 'self-loading-rifle').'"')
        ->toContain('href="'.route('divisions.show', 'shotgun').'"')
        ->toContain('href="'.route('calendar.month').'">Calendar</a>')
        ->not->toContain('page="home"')
        ->not->toContain('href="'.route('divisions.show', 'air-rifle').'"');
});

it('lists handgun sports on the handgun page and keeps precision rifle on bolt-action', function () {
    Discipline::factory()->create([
        'slug' => 'ipsc-practical',
        'name' => 'IPSC',
        'family' => DisciplineFamily::Handgun,
        'short_blurb' => 'Practical pistol on stages against the clock.',
    ])->syncDivisions([Division::Handgun]);

    Discipline::factory()->create([
        'slug' => 'precision-rifle-test',
        'name' => 'Precision Rifle',
        'family' => DisciplineFamily::Rifle,
        'short_blurb' => 'Unknown-distance steel.',
    ])->syncDivisions([Division::BoltActionRifle]);

    $this->get(route('divisions.show', 'handgun'))
        ->assertOk()
        ->assertSee('IPSC')
        ->assertDontSee('Precision Rifle');

    $this->get(route('divisions.show', 'bolt-action-rifle'))
        ->assertOk()
        ->assertSee('Precision Rifle')
        ->assertDontSee('>IPSC<', false);
});

it('lists 3-Gun under handgun, self-loading rifle, and shotgun', function () {
    Discipline::factory()->create([
        'slug' => '3-gun',
        'name' => '3-Gun',
        'family' => DisciplineFamily::Multi,
        'short_blurb' => 'Rifle, pistol and shotgun on the same course.',
    ])->syncDivisions([
        Division::Handgun,
        Division::SelfLoadingRifle,
        Division::Shotgun,
    ]);

    foreach (['handgun', 'self-loading-rifle', 'shotgun'] as $division) {
        $this->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee('3-Gun');
    }
});

it('returns 404 for the air rifle division while it is off', function () {
    $this->get(route('divisions.show', 'air-rifle'))->assertNotFound();
});

it('shows a youtube thumbnail only when a real youtube link was saved', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'idpa-video',
        'name' => 'IDPA',
        'short_blurb' => 'Defensive pistol.',
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertDontSee('sport-video', false);

    DisciplineVideo::query()->create([
        'discipline_id' => $discipline->id,
        'title' => 'What to expect',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'video_id' => 'dQw4w9WgXcQ',
        'sort_order' => 0,
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
        ->assertSee('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false)
        ->assertSee('What to expect');

    expect(YouTube::idFromUrl('https://example.com/watch?v=dQw4w9WgXcQ'))->toBeNull()
        ->and(YouTube::idFromUrl('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ');
});

it('links the sport organisation short name to its website', function () {
    $federation = Organisation::factory()->create([
        'name' => 'South African Precision Rifle Federation',
        'short_name' => 'SAPRF',
        'website_url' => 'https://saprf.co.za',
        'type' => OrganisationType::Federation,
    ]);

    $discipline = Discipline::factory()->create([
        'name' => 'Precision Rifle',
        'short_blurb' => 'Unknown-distance steel.',
        'federation_organisation_id' => $federation->id,
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('https://saprf.co.za', false)
        ->assertSee('SAPRF')
        ->assertDontSee('Governed by', false);
});

it('shows a handgun sponsor on handgun and not on shotgun', function () {
    $provider = Provider::factory()->create();
    $slot = AdSlot::query()->create([
        'page' => AdPage::Disciplines->value,
        'slot' => PlacementSlot::CategorySponsor->value,
        'name' => 'Division sponsor',
        'price_cents' => 180000,
        'is_active' => true,
    ]);

    Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::CategorySponsor,
        'division' => Division::Handgun->value,
        'headline' => 'Handgun optic',
        'body' => 'For pistol shooters.',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 180000,
        'is_active' => true,
    ]);

    expect(AdPlacements::for(AdPage::Disciplines, PlacementSlot::CategorySponsor, 1, Division::Handgun))->toHaveCount(1)
        ->and(AdPlacements::for(AdPage::Disciplines, PlacementSlot::CategorySponsor, 1, Division::Shotgun))->toHaveCount(0);

    $this->get(route('divisions.show', 'handgun'))
        ->assertOk()
        ->assertSee('Handgun optic');

    $this->get(route('divisions.show', 'shotgun'))
        ->assertOk()
        ->assertDontSee('Handgun optic');
});

it('lists a club on its division page and in the filtered directory', function () {
    $handgun = Discipline::factory()->create([
        'slug' => 'ipsc-practical',
        'name' => 'IPSC',
        'family' => DisciplineFamily::Handgun,
    ]);
    $handgun->syncDivisions([Division::Handgun]);

    $rifle = Discipline::factory()->create([
        'slug' => 'precision-rifle-test',
        'name' => 'Precision Rifle',
        'family' => DisciplineFamily::Rifle,
    ]);
    $rifle->syncDivisions([Division::BoltActionRifle]);

    $pistolClub = Organisation::factory()->create(['name' => 'Highveld Pistol Club']);
    $pistolClub->attachDiscipline($handgun);

    $rifleClub = Organisation::factory()->create(['name' => 'Highveld Rifle Club']);
    $rifleClub->attachDiscipline($rifle);

    $this->get(route('divisions.show', 'handgun'))
        ->assertOk()
        ->assertSee('Highveld Pistol Club')
        ->assertDontSee('Highveld Rifle Club')
        ->assertSee('All Handgun clubs');

    $this->get(route('clubs.index', ['division' => 'handgun']))
        ->assertOk()
        ->assertSee('Highveld Pistol Club')
        ->assertDontSee('Highveld Rifle Club');
});

it('shows a sport advert under the division advert and keeps it off other sports', function () {
    $bolt = [
        'precision-rifle' => 'Precision Rifle',
        'prs' => 'PRS',
        'pr22-rimfire' => 'PR22',
        'nrl-hunter' => 'NRL Hunter',
        'gong-shooting' => 'Gong Shooting',
        'f-class' => 'F-Class',
        'benchrest' => 'Benchrest',
    ];

    $sports = collect($bolt)->map(fn (string $name, string $slug) => tap(
        Discipline::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'family' => DisciplineFamily::Rifle,
        ]),
        fn (Discipline $sport) => $sport->syncDivisions([Division::BoltActionRifle]),
    ));

    $slot = AdSlot::query()->create([
        'page' => AdPage::Disciplines->value,
        'slot' => PlacementSlot::CategorySponsor->value,
        'name' => 'Sport sponsor',
        'price_cents' => 180000,
        'is_active' => true,
    ]);

    $provider = Provider::factory()->create();

    Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::CategorySponsor,
        'division' => Division::BoltActionRifle,
        'headline' => 'Bolt scope',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 90000,
        'is_active' => true,
    ]);

    $prsAdvert = Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::CategorySponsor,
        'division' => Division::BoltActionRifle,
        'headline' => 'PRS timer',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 120000,
        'is_active' => true,
    ]);
    $prsAdvert->disciplines()->sync($sports->only(['precision-rifle', 'prs', 'pr22-rimfire', 'nrl-hunter'])->pluck('id'));

    $paperAdvert = Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Rest and flags',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 80000,
        'is_active' => true,
    ]);
    $paperAdvert->disciplines()->sync($sports->only(['f-class', 'benchrest'])->pluck('id'));

    $this->get(route('disciplines.show', 'prs'))
        ->assertOk()
        ->assertSee('Bolt scope')
        ->assertSee('PRS timer')
        ->assertDontSee('Rest and flags');

    $this->get(route('disciplines.show', 'gong-shooting'))
        ->assertOk()
        ->assertSee('Bolt scope')
        ->assertDontSee('PRS timer')
        ->assertDontSee('Rest and flags');

    $this->get(route('disciplines.show', 'f-class'))
        ->assertOk()
        ->assertSee('Bolt scope')
        ->assertSee('Rest and flags')
        ->assertDontSee('PRS timer');

    $this->get(route('divisions.show', 'bolt-action-rifle'))
        ->assertOk()
        ->assertSee('Bolt scope')
        ->assertDontSee('PRS timer')
        ->assertDontSee('Rest and flags');

    $match = Event::factory()->create(['title' => 'Highveld PRS']);
    $match->attachDiscipline($sports->get('prs'));

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertSee('Bolt scope')
        ->assertSee('PRS timer')
        ->assertDontSee('Rest and flags');
});
