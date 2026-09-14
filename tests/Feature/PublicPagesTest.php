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
use Illuminate\Support\Facades\Route;

it('renders the home page instead of the Laravel welcome screen', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Find your next')
        ->assertSee('Match finder')
        ->assertDontSee('Let’s get started');
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
        ->assertSee('Host federation')
        ->assertSee('Entry details')
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
