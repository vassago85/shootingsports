<?php

use App\Enums\DisciplineFamily;

beforeEach(function () {
    $this->withoutVite();
});

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\PublicCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

it('renders the home page instead of the Laravel welcome screen', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Find your next')
        ->assertSee('Match finder')
        ->assertDontSee('Let’s get started');
});

it('lists the next ten home-rail matches inside 30 days', function () {
    foreach (range(1, 11) as $day) {
        Event::factory()->confirmed()->create([
            'title' => sprintf('Window match %02d', $day),
            'starts_at' => now()->addDays($day),
        ]);
    }

    Event::factory()->confirmed()->create([
        'title' => 'Far horizon match',
        'starts_at' => now()->addDays(45),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Next 30 days')
        ->assertSee('Window match 01')
        ->assertSee('Window match 10')
        ->assertDontSee('Window match 11')
        ->assertDontSee('Far horizon match');
});

it('lists upcoming matches on the calendar', function () {
    Event::factory()->confirmed()->create(['title' => 'Highveld Club Match']);
    Event::factory()->draft()->create(['title' => 'Hidden draft']);

    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee('Highveld Club Match')
        ->assertDontSee('Hidden draft');
});

it('shows a published discipline page and noindexes thin province slices', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'family' => DisciplineFamily::Rifle,
        'short_blurb' => 'Unknown-distance steel.',
        'is_published' => true,
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('Precision Rifle')
        ->assertDontSee('<meta name="robots" content="noindex,follow">', false);

    $this->get(route('disciplines.province', [$discipline->slug, 'gauteng']))
        ->assertOk()
        ->assertSee('in Gauteng')
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('Precision Rifle');

    expect(Cache::get(PublicCache::key('discipline.precision-rifle.all')))->toBeNull();
});

it('invalidates public stats without leaking versioned cache keys', function () {
    Cache::put(PublicCache::key('stats'), ['clubs' => 9], 600);
    Cache::put('public.v1.discipline.precision-rifle.all', ['stale' => true], 600);

    PublicCache::bump();

    expect(Cache::get(PublicCache::key('stats')))->toBeNull()
        ->and(PublicCache::key('stats'))->toBe('public.stats')
        ->and(PublicCache::key('stats'))->not->toContain('.v');
});

it('shows club, range, supplier and match pages by slug', function () {
    $club = Organisation::factory()->verified()->create([
        'slug' => 'pretoria-precision-rifle-club',
        'name' => 'Pretoria Precision Rifle Club',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $venue = Venue::factory()->create([
        'slug' => 'pprc-range-pretoria',
        'name' => 'PPRC Range',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'slug' => 'pprc-prs-club-match-sep',
        'title' => 'PPRC PRS Club Match',
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
    ]);
    $provider = Provider::factory()->create([
        'slug' => 'ridgeline-rifleworks',
        'name' => 'Ridgeline Rifleworks',
        'category' => ProviderCategory::Gunsmith,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('clubs.show', $club->slug))->assertOk()->assertSee('Pretoria Precision Rifle Club');
    $this->get(route('ranges.show', $venue->slug))->assertOk()->assertSee('PPRC Range');
    $this->get(route('matches.show', $event->slug))->assertOk()->assertSee('PPRC PRS Club Match');
    $this->get(route('suppliers.show', $provider->slug))->assertOk()->assertSee('Ridgeline Rifleworks');
});

it('binds public listing routes to the slug column', function () {
    $routes = Route::getRoutes();

    expect($routes->getByName('matches.show')->bindingFields())->toBe(['event' => 'slug'])
        ->and($routes->getByName('clubs.show')->bindingFields())->toBe(['organisation' => 'slug'])
        ->and($routes->getByName('federations.show')->bindingFields())->toBe(['organisation' => 'slug'])
        ->and($routes->getByName('ranges.show')->bindingFields())->toBe(['venue' => 'slug'])
        ->and($routes->getByName('suppliers.show')->bindingFields())->toBe(['provider' => 'slug']);
});

it('renders a federation-hosted match with entry url and fees', function () {
    $federation = Organisation::factory()->create([
        'slug' => 'saprf-test',
        'name' => 'South African Precision Rifle Federation',
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
        'logo_path' => 'organisation-logos/saprf.png',
        'website_url' => 'https://saprf.co.za',
    ]);
    $venue = Venue::factory()->create([
        'slug' => 'balmoral-farm-test',
        'name' => 'Balmoral Farm',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'slug' => 'saprf-pr22-mp-provincial-test',
        'title' => 'Rimfire PR22 MP Provincial',
        'host_organisation_id' => $federation->id,
        'venue_id' => $venue->id,
        'status' => EventStatus::EntriesOpen,
        'entry_url' => 'https://saprf.co.za/events/111',
        'entry_fee_cents' => 90000,
        'member_fee_cents' => 70000,
    ]);

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee('Rimfire PR22 MP Provincial')
        ->assertSee('South African Precision Rifle Federation')
        ->assertSee('Enter here')
        ->assertDontSee('Host club');
});

it('returns 404 for draft events and unpublished listings', function () {
    $draft = Event::factory()->draft()->create(['slug' => 'secret-match']);
    $archived = Organisation::factory()->create([
        'slug' => 'gone-club',
        'status' => ListingStatus::Archived,
    ]);

    $this->get(route('matches.show', $draft->slug))->assertNotFound();
    $this->get(route('clubs.show', $archived->slug))->assertNotFound();
});

it('keeps federation listings off the club route', function () {
    $federation = Organisation::factory()->create([
        'slug' => 'saprf',
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('clubs.show', $federation->slug))->assertNotFound();
    $this->get(route('federations.show', $federation->slug))->assertOk();
});

it('serves split sitemaps and ical feeds', function () {
    $club = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $discipline = Discipline::factory()->create(['is_published' => true]);
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $club->id,
        'title' => 'League Round',
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');
    $this->get('/sitemaps/events.xml')->assertOk()->assertSee(route('matches.show', $event->slug), false);
    $this->get(route('ical.organisation', $club->slug))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8')
        ->assertSee('BEGIN:VEVENT')
        ->assertSee('League Round');
    $this->get(route('ical.discipline', $discipline->slug))
        ->assertOk()
        ->assertSee('BEGIN:VCALENDAR');
});

it('lists ranges and shows enquire CTAs without public emails', function () {
    $venue = Venue::factory()->create([
        'slug' => 'smoke-range',
        'name' => 'Smoke Test Range',
        'status' => ListingStatus::Published,
        'tier' => ProviderTier::Featured,
    ]);

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('Smoke Test Range')
        ->assertSee('Featured');

    $this->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('Enquire via platform')
        ->assertSee('Featured');
});

it('serves contact and advertise enquiry forms', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Contact Shooting Sports')
        ->assertSee('company_website');

    $this->get(route('advertise'))
        ->assertOk()
        ->assertSee('Advertise on the register');
});

it('hyphenates province slugs in public urls', function () {
    expect(Province::WesternCape->urlSlug())->toBe('western-cape')
        ->and(Province::fromUrlSlug('western-cape'))->toBe(Province::WesternCape)
        ->and(Province::fromUrlSlug('no-such-place'))->toBeNull();
});

it('falls a match card cover back to the host organisation logo', function () {
    $federation = Organisation::factory()->create([
        'name' => 'South African Precision Rifle Federation',
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
        'logo_path' => 'organisation-logos/saprf.png',
    ]);
    $event = Event::factory()->confirmed()->create([
        'title' => 'Centrefire 1 Day',
        'host_organisation_id' => $federation->id,
        'banner_path' => null,
        'banner_media_id' => null,
    ]);
    $event->load('hostOrganisation');

    expect($event->bannerUrl())->toBeNull()
        ->and($event->coverImageUrl())->toEndWith('/media/organisation-logos/saprf.png');

    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee('/media/organisation-logos/saprf.png', false)
        ->assertSee('Centrefire 1 Day')
        ->assertDontSee('No club banner yet')
        ->assertDontSee('No match banner yet');
});

it('prefers the match banner over the host logo', function () {
    $series = Organisation::factory()->create([
        'type' => OrganisationType::Series,
        'status' => ListingStatus::Published,
        'logo_path' => 'organisation-logos/series.png',
    ]);
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $series->id,
        'banner_path' => 'event-banners/own.jpg',
    ]);
    $event->load('hostOrganisation');

    expect($event->coverImageUrl())->toEndWith('/media/event-banners/own.jpg');
});

it('falls a club match cover back to the parent federation logo', function () {
    $federation = Organisation::factory()->create([
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
        'logo_path' => 'organisation-logos/parent-fed.png',
    ]);
    $club = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
        'parent_id' => $federation->id,
        'logo_path' => null,
    ]);
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $club->id,
        'banner_path' => null,
        'banner_media_id' => null,
    ]);
    $event->load('hostOrganisation.parent');

    expect($event->coverImageUrl())->toEndWith('/media/organisation-logos/parent-fed.png');
});
