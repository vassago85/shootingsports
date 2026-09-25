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
use App\Enums\VenueAccess;
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
        ->assertSee('Shooting matches in South Africa')
        ->assertSee('Find your')
        ->assertDontSee('Match finder')
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
        ->assertRedirect(route('clubs.landing', ['gauteng', $discipline->slug]));

    $this->get(route('clubs.landing', ['gauteng', $discipline->slug]))
        ->assertOk()
        ->assertSee('in Gauteng')
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('Precision Rifle');

    expect(Cache::get(PublicCache::key('discipline.precision-rifle.all')))->toBeNull();
});

it('shows a discipline picture on the sport page and the sports directory', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'precision-rifle-photo',
        'name' => 'Precision Rifle Photo',
        'image_path' => 'discipline-images/prs.jpg',
        'is_published' => true,
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('discipline-images/prs.jpg', false)
        ->assertSee('has-photo', false);

    $this->get(route('disciplines.index'))
        ->assertOk()
        ->assertSee('discipline-images/prs.jpg', false);
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

it('lists Feather Fur and Target under reloading and as a secondary optics supplier', function () {
    $this->get(route('suppliers.show', 'feather-fur-and-target'))
        ->assertOk()
        ->assertSee('Feather Fur and Target')
        ->assertSee('Reloading components, equipment, and optics in Pretoria.')
        ->assertSee('Faerie Glen')
        ->assertSee('https://ffat.co.za', false)
        ->assertDontSee('orders@ffat.co.za');

    $this->get(route('suppliers.category', 'reloading-components'))
        ->assertOk()
        ->assertSeeInOrder(['Feather Fur and Target', 'Secondary']);

    $this->get(route('suppliers.category', 'optics'))
        ->assertOk()
        ->assertSee('Feather Fur and Target')
        ->assertSee('Secondary')
        ->assertSee('Primary · Reloading components');
});

it('lists MC Tactical as a dealer that also offers optics, chassis, and reloading', function () {
    $this->get(route('suppliers.show', 'mc-tactical'))
        ->assertOk()
        ->assertSee('MC Tactical')
        ->assertSee('Firearms, optics, chassis, and reloading supplies in Pretoria.')
        ->assertSee('Garsfontein')
        ->assertSee('https://mctactical.co.za', false)
        ->assertDontSee('sales@mctactical.co.za');

    $this->get(route('suppliers.category', 'dealer'))
        ->assertOk()
        ->assertSee('MC Tactical')
        ->assertDontSee('Secondary');

    $this->get(route('suppliers.category', 'optics'))
        ->assertOk()
        ->assertSee('MC Tactical')
        ->assertSee('Primary · Dealer');

    $this->get(route('suppliers.category', 'chassis-stocks'))
        ->assertOk()
        ->assertSee('MC Tactical')
        ->assertSee('Secondary');
});

it('lists Out There Sport Shooting Club as a public range in Bashewa', function () {
    $this->get(route('ranges.show', 'out-there-sport-shooting-club'))
        ->assertOk()
        ->assertSee('Out There Sport Shooting Club')
        ->assertSee('Moria Saai Farm, Garsfontein Road, Bashewa, Pretoria, 0056')
        ->assertSee('300 m')
        ->assertSee('Public')
        ->assertSee('R150')
        ->assertSee('Pretoria');
});

it('lists Blue Gum Valley Shooting Range as a public range near Bronkhorstspruit', function () {
    $this->get(route('ranges.show', 'blue-gum-valley-shooting-range'))
        ->assertOk()
        ->assertSee('Blue Gum Valley Shooting Range')
        ->assertSee('30 Knoppiesfontein Road, Onbekend, Bronkhorstspruit')
        ->assertSee('Public')
        ->assertSee('Pretoria');

    $this->get(route('ranges.index', ['province' => 'gauteng']))
        ->assertOk()
        ->assertSee('Blue Gum Valley Shooting Range')
        ->assertSee('Bronkhorstspruit');
});

it('lists Zimbi as a dealer that also offers ammunition, optics, reloading, and safes', function () {
    $this->get(route('suppliers.show', 'zimbi'))
        ->assertOk()
        ->assertSee('Zimbi')
        ->assertSee('Specialist hunting and firearms shop in Pretoria.')
        ->assertSee('Persequor')
        ->assertSee('https://zimbi.co.za', false)
        ->assertDontSee('zimbi@zimbi.co.za');

    $this->get(route('suppliers.category', 'dealer'))
        ->assertOk()
        ->assertSee('Zimbi')
        ->assertDontSee('Secondary');

    $this->get(route('suppliers.category', 'ammunition'))
        ->assertOk()
        ->assertSee('Zimbi')
        ->assertSee('Secondary')
        ->assertSee('Primary · Dealer');

    $this->get(route('suppliers.category', 'safes-storage'))
        ->assertOk()
        ->assertSee('Zimbi')
        ->assertSee('Primary · Dealer');
});

it('lists each Safari Outdoor store in its own province, with shooting lines as secondary', function () {
    $this->get(route('suppliers.show', 'safari-outdoor-pretoria'))
        ->assertOk()
        ->assertSee('Safari Outdoor Pretoria')
        ->assertSee('Lynnwood Bridge')
        ->assertSee('https://safarioutdoor.co.za', false)
        ->assertDontSee('info@so.co.za');

    $this->get(route('suppliers.category', 'dealer'))
        ->assertOk()
        ->assertSee('Safari Outdoor Pretoria')
        ->assertSee('Safari Outdoor Stellenbosch')
        ->assertDontSee('Secondary');

    $this->get(route('suppliers.province', ['dealer', 'western-cape']))
        ->assertOk()
        ->assertSee('Safari Outdoor Stellenbosch')
        ->assertDontSee('Safari Outdoor Pretoria');

    $this->get(route('suppliers.province', ['optics', 'mpumalanga']))
        ->assertOk()
        ->assertSee('Safari Outdoor Nelspruit')
        ->assertSee('Secondary')
        ->assertSee('Primary · Dealer');
});

it('keeps distributor accounts off the public register', function () {
    $this->get(route('suppliers.category', 'distributor'))->assertNotFound();

    $this->get(route('suppliers.show', 'whylo'))->assertNotFound();

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertDontSee('Whylo')
        ->assertDontSee('>Distributor<', false);
});

it('binds public listing routes to the slug column', function () {
    $routes = Route::getRoutes();

    expect($routes->getByName('matches.show')->bindingFields())->toBe(['event' => 'slug'])
        ->and($routes->getByName('clubs.show')->bindingFields())->toBe(['organisation' => 'slug'])
        ->and($routes->getByName('federations.show')->bindingFields())->toBe(['organisation' => 'slug'])
        ->and($routes->getByName('suppliers.show')->bindingFields())->toBe(['provider' => 'slug']);

    // Ranges intentionally use a plain-string slug (no implicit model
    // binding) so the controller can look the slug up against
    // `venue_aliases` and 301 old slugs after a merge.
    expect($routes->getByName('ranges.show')->parameterNames())->toBe(['slug']);
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
        ->assertHeader('x-robots-tag', 'noindex, nofollow')
        ->assertSee('BEGIN:VEVENT')
        ->assertSee('League Round');
    $this->get(route('ical.discipline', $discipline->slug))
        ->assertOk()
        ->assertHeader('x-robots-tag', 'noindex, nofollow')
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

it('filters ranges by public access and minimum distance', function () {
    Venue::factory()->create([
        'name' => 'Open Long Range',
        'slug' => 'open-long-range',
        'status' => ListingStatus::Published,
        'access' => VenueAccess::Public,
        'max_distance_m' => 1000,
    ]);
    Venue::factory()->create([
        'name' => 'Closed Short Range',
        'slug' => 'closed-short-range',
        'status' => ListingStatus::Published,
        'access' => VenueAccess::MembersOnly,
        'max_distance_m' => 100,
    ]);

    $this->get(route('ranges.index', ['visitors' => 1, 'min_distance' => 600]))
        ->assertOk()
        ->assertSee('Find a shooting range')
        ->assertSee('Open Long Range')
        ->assertDontSee('Closed Short Range');
});

it('filters clubs that welcome visitors', function () {
    Organisation::factory()->create([
        'name' => 'Open Club',
        'slug' => 'open-club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'visitors_welcome' => true,
    ]);
    Organisation::factory()->create([
        'name' => 'Closed Club',
        'slug' => 'closed-club',
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'visitors_welcome' => false,
    ]);

    $this->get(route('clubs.index', ['visitors' => 1]))
        ->assertOk()
        ->assertSee('Find your club')
        ->assertSee('Open Club')
        ->assertDontSee('Closed Club');
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

    // The list-view match board is intentionally image-free (dense rows,
    // no banners) so the fallback logo now only surfaces on entity show
    // pages, which still render DOPE cards via <x-event-card>.
    $this->get(route('federations.show', $federation->slug))
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
