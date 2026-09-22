<?php

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\Mockups\MockupCatalog;
use Database\Seeders\DisciplineSeeder;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the redesign mockup index', function () {
    $this->get(route('mockups.index'))
        ->assertOk()
        ->assertSee('Redesign mockups')
        ->assertSee('Find a match')
        ->assertSee('My Shooting')
        ->assertSee('Following')
        ->assertSee('Club desk')
        ->assertSee('Club management')
        ->assertSee('iOS and Android')
        ->assertSee('Needs attention');
});

it('renders each public and admin mockup', function (string $route) {
    $this->get(route($route))->assertOk();
})->with([
    'mockups.home',
    'mockups.matches',
    'mockups.matches.calendar',
    'mockups.matches.map',
    'mockups.match',
    'mockups.sports',
    'mockups.sport',
    'mockups.clubs',
    'mockups.club',
    'mockups.ranges',
    'mockups.range',
    'mockups.industry',
    'mockups.business',
    'mockups.search',
    'mockups.account',
    'mockups.account.following',
    'mockups.onboarding',
    'mockups.manage.index',
    'mockups.manage.matches',
    'mockups.manage.matches.new',
    'mockups.manage.profile',
    'mockups.apps',
    'mockups.admin.dashboard',
    'mockups.admin.matches',
    'mockups.admin.matches.edit',
    'mockups.admin.clubs',
    'mockups.admin.ranges',
    'mockups.admin.sports',
    'mockups.admin.industry',
    'mockups.admin.submissions',
    'mockups.admin.quality',
    'mockups.admin.duplicates',
    'mockups.admin.reports',
    'mockups.admin.advertising',
]);

it('keeps the live hero and opens a division onto its disciplines', function () {
    $this->seed(DisciplineSeeder::class);

    $this->get(route('mockups.home'))
        ->assertOk()
        ->assertSee('Find your')
        ->assertSee('What are you interested in?')
        ->assertSee('Handgun')
        ->assertSee(route('mockups.sports', ['division' => 'handgun']), false)
        ->assertDontSee('Sponsored')
        ->assertDontSee('Advertise here');

    $this->get(route('mockups.sports', ['division' => 'bolt-action-rifle']))
        ->assertOk()
        ->assertSee('Bolt-action rifle')
        ->assertSee('Precision Rifle')
        ->assertSee('Choose a discipline')
        ->assertSee(route('mockups.sport', ['slug' => 'precision-rifle']), false);

    $this->get(route('mockups.sport', ['slug' => 'precision-rifle']))
        ->assertOk()
        ->assertSee('Choose a sub-discipline')
        ->assertSee('PR22 Rimfire')
        ->assertSee(route('mockups.matches', ['sport' => 'pr22-rimfire']), false);

    $this->get(route('mockups.matches', ['sport' => 'pr22-rimfire']))
        ->assertOk()
        ->assertSee('PR22 Rimfire')
        ->assertSee('The match list')
        ->assertSee('>List</a>', false)
        ->assertSee(route('mockups.matches.calendar', ['sport' => 'pr22-rimfire']), false);

    $matches = $this->get(route('mockups.matches', ['division' => 'handgun']));
    $matches->assertOk()
        ->assertDontSee('Advertise here')
        ->assertDontSee('Sponsored');
    expect(substr_count($matches->getContent(), 'aria-label="Partner"'))->toBe(0);

    $this->get(route('mockups.sports', ['division' => 'handgun']))
        ->assertOk()
        ->assertSee(route('mockups.matches', ['sport' => 'steel-challenge']), false);
});

it('says when match filters match nothing', function () {
    $this->get(route('mockups.matches', ['beginner' => 1]))
        ->assertOk()
        ->assertSee('No matches match these filters.')
        ->assertSee('None of the current upcoming matches carry it.');
});

it('shows labelled supplier ads and the matches find suppliers you tabs', function () {
    $sponsors = app(MockupCatalog::class)->sponsors();
    $businesses = app(MockupCatalog::class)->directoryBusinesses();

    $home = $this->get(route('mockups.apps'))
        ->assertOk()
        ->assertSee('Matches')
        ->assertSee('Find')
        ->assertSee('Suppliers')
        ->assertSee('You')
        ->assertSee('Dark')
        ->assertSee('Larger text');

    if ($sponsors->isNotEmpty()) {
        $home->assertSee('Sponsored')
            ->assertSee($sponsors->first()['headline'])
            ->assertSee('They do not change the match order.');
    }

    $directory = $this->get(route('mockups.apps', ['screen' => 'suppliers']))
        ->assertOk()
        ->assertSee('All suppliers');

    if ($businesses->isNotEmpty()) {
        $directory->assertSee($businesses->first()['name']);
    }

    $this->get(route('mockups.apps', ['screen' => 'find']))
        ->assertOk()
        ->assertSee('Clubs')
        ->assertSee('Ranges')
        ->assertSee('Sports');
});

it('filters the app sports list by category and search', function () {
    $sports = app(MockupCatalog::class)->sports();
    $rifle = $sports->firstWhere('family', 'rifle');
    $other = $sports->first(fn (array $sport): bool => $sport['family'] !== null && $sport['family'] !== 'rifle');

    $this->get(route('mockups.apps', ['screen' => 'sports']))
        ->assertOk()
        ->assertSee('Search sports')
        ->assertSee('All');

    if ($rifle !== null && $other !== null) {
        $this->get(route('mockups.apps', ['screen' => 'sports', 'family' => 'rifle']))
            ->assertOk()
            ->assertSee($rifle['name'])
            ->assertDontSee($other['name']);
    }

    $this->get(route('mockups.apps', ['screen' => 'sports', 'q' => 'zzzz-no-sport']))
        ->assertOk()
        ->assertSee('No sports match that filter.');
});

it('hides other provinces when the app location is gauteng', function () {
    $matches = app(MockupCatalog::class)->publicMatches();
    $gauteng = $matches->firstWhere('province_value', 'gauteng');
    $cape = $matches->first(fn (array $match): bool => $match['province_value'] === 'western_cape');

    $page = $this->get(route('mockups.apps'))
        ->assertOk()
        ->assertSee('from your account');

    $page = $this->get(route('mockups.apps', ['province' => 'gauteng']))
        ->assertOk()
        ->assertSee('Anywhere')
        ->assertSee('from your account');

    if ($gauteng !== null) {
        $page->assertSee($gauteng['title']);
    }

    if ($cape !== null && $gauteng !== null && $cape['title'] !== $gauteng['title']) {
        $page->assertDontSee($cape['title']);
    }

    $this->get(route('mockups.apps', ['province' => 'gauteng', 'km' => 50]))
        ->assertOk()
        ->assertSee('Within 50 km of Gauteng');

    if ($cape !== null) {
        $this->get(route('mockups.apps', ['province' => 'all']))
            ->assertOk()
            ->assertSee('All of South Africa')
            ->assertSee($cape['title']);
    }
});

it('shows a month calendar in the app', function () {
    $month = now()->timezone('Africa/Johannesburg');
    $catalog = app(MockupCatalog::class);
    $grid = $catalog->calendar($month->format('Y-m'), ['province' => Province::Gauteng->value]);
    $home = collect($grid['agenda'])->first(fn (array $match): bool => $match['date'] === $month->toDateString());
    $away = collect($catalog->calendar($month->format('Y-m'), [])['agenda'])
        ->first(fn (array $match): bool => $match['province_value'] === Province::WesternCape->value);

    $page = $this->get(route('mockups.apps', ['screen' => 'calendar']))
        ->assertOk()
        ->assertSee('Calendar')
        ->assertSee($month->format('F Y'))
        ->assertSee('Previous month')
        ->assertSee('List');

    if ($home !== null) {
        $page->assertSee($home['title']);
    }

    if ($away !== null && ($home === null || $away['title'] !== $home['title'])) {
        $page->assertDontSee($away['title']);
    }

    $next = $month->copy()->addMonth();

    $this->get(route('mockups.apps', ['screen' => 'calendar', 'month' => $next->format('Y-m')]))
        ->assertOk()
        ->assertSee($next->format('F Y'));

    $this->get(route('mockups.apps', ['screen' => 'today']))
        ->assertOk()
        ->assertSee('Calendar');
});

it('opens clubs and series on the account province', function () {
    $clubs = app(MockupCatalog::class)->clubs();
    $home = $clubs->firstWhere('province_value', Province::Gauteng->value);
    $away = $clubs->first(fn (array $club): bool => $club['province_value'] === Province::WesternCape->value);

    $page = $this->get(route('mockups.apps', ['screen' => 'clubs']))
        ->assertOk()
        ->assertSee('Clubs & series')
        ->assertSee('Membership clubs and match series in Gauteng.');

    if ($home !== null) {
        $page->assertSee($home['name'])->assertSee($home['type']);
    }

    if ($away !== null && ($home === null || $away['name'] !== $home['name'])) {
        $page->assertDontSee($away['name']);
    }

    if ($away !== null) {
        $this->get(route('mockups.apps', ['screen' => 'clubs', 'province' => Province::WesternCape->value]))
            ->assertOk()
            ->assertSee($away['name'])
            ->assertSee('Your account is still Gauteng');
    }
});

it('shows store cancel, privacy and account deletion on the app mockups', function () {
    $pricing = config('plans.pricing');

    $this->get(route('mockups.apps', ['screen' => 'plans']))
        ->assertOk()
        ->assertSee($pricing['annual']['display'])
        ->assertSee($pricing['monthly']['display'])
        ->assertSee('Privacy policy')
        ->assertSee('Terms of use')
        ->assertSee('The plan renews on its own');

    $this->get(route('mockups.apps', ['screen' => 'you']))
        ->assertOk()
        ->assertSee('Cancel subscription')
        ->assertSee('Where you shoot')
        ->assertSee('Your data')
        ->assertSee('Delete account');

    $this->get(route('mockups.apps', ['screen' => 'data']))
        ->assertOk()
        ->assertSee('We do not sell personal information')
        ->assertSee('Download my data')
        ->assertSee('hello@shootingsports.co.za');

    $this->get(route('mockups.apps', ['screen' => 'delete']))
        ->assertOk()
        ->assertSee('Deleting the account does not cancel a store subscription')
        ->assertSee('Delete my account');

    $this->get(route('mockups.apps', ['screen' => 'signin']))
        ->assertOk()
        ->assertSee('Sign in with Apple')
        ->assertSee('Continue with Google')
        ->assertSee('18 or older');

    $this->get(route('mockups.apps', ['screen' => 'alerts', 'emails' => 'off']))
        ->assertOk()
        ->assertSee('You are unsubscribed from optional emails');

    $this->get(route('mockups.apps', ['screen' => 'not-a-screen']))
        ->assertOk()
        ->assertSee('Coming up')
        ->assertDontSee('Cancel Pro?');
});

it('starts a match packing list from the sport basics and keeps an added item', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'precision-rifle')->firstOrFail();
    $event = Event::factory()->create(['title' => 'Kalahari Steel']);
    $event->attachDiscipline($sport, primary: true);

    $this->get(route('mockups.apps', ['screen' => 'pack']))
        ->assertOk()
        ->assertSee('Precision Rifle')
        ->assertSee('basics');

    $this->get(route('mockups.apps', ['screen' => 'pack', 'sport' => 'precision-rifle']))
        ->assertOk()
        ->assertSee('These basics come with Precision Rifle')
        ->assertSee('Bipod')
        ->assertSee('Pack for Kalahari Steel');

    $this->get(route('mockups.apps', [
        'screen' => 'pack',
        'match' => $event->slug,
        'item' => 'Dope card',
        'on' => ['bipod'],
    ]))
        ->assertOk()
        ->assertSee('Dope card')
        ->assertSee('Added for this match')
        ->assertSee('app-check on');
});

it('searches upcoming matches and keeps the account follows on the screen', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'precision-rifle')->firstOrFail();
    $club = Organisation::factory()->create([
        'name' => 'Kalahari Rifle Club',
        'description' => 'Hosts precision rifle matches.',
    ]);
    $event = Event::factory()->create([
        'title' => 'Kalahari Steel',
        'host_organisation_id' => $club->id,
        'venue_id' => Venue::factory()->create(['province' => Province::Gauteng])->id,
    ]);
    $event->attachDiscipline($sport, primary: true);

    $this->get(route('mockups.apps', ['screen' => 'search']))
        ->assertOk()
        ->assertSee('Following')
        ->assertSee('Precision Rifle')
        ->assertSee('Kalahari Rifle Club')
        ->assertSee('Kalahari Steel');

    $this->get(route('mockups.apps', ['screen' => 'search', 'q' => 'no-such-match']))
        ->assertOk()
        ->assertSee('Following')
        ->assertSee('No upcoming matches in Gauteng match this search')
        ->assertDontSee('Kalahari Steel');
});

it('opens a handgun sport onto its upcoming matches', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'steel-challenge')->firstOrFail();
    $event = Event::factory()->create([
        'title' => 'Steel Saturday',
        'venue_id' => Venue::factory()->create(['province' => Province::Gauteng])->id,
    ]);
    $event->attachDiscipline($sport, primary: true);

    $this->get(route('mockups.apps', ['screen' => 'sport', 'sport' => 'steel-challenge', 'family' => 'handgun']))
        ->assertOk()
        ->assertSee('Upcoming')
        ->assertSee('Steel Saturday')
        ->assertDontSee('These basics come with Steel');
});

it('filters upcoming matches to those close to you', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'steel-challenge')->firstOrFail();
    $near = Venue::factory()->create([
        'lat' => -25.7479,
        'lng' => 28.2293,
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
    ]);
    $far = Venue::factory()->create([
        'lat' => -33.9249,
        'lng' => 18.4241,
        'town' => 'Cape Town',
        'province' => Province::WesternCape,
    ]);
    $close = Event::factory()->create([
        'title' => 'Steel Saturday',
        'venue_id' => $near->id,
    ]);
    $away = Event::factory()->create([
        'title' => 'Steel Cape',
        'venue_id' => $far->id,
    ]);
    $close->attachDiscipline($sport, primary: true);
    $away->attachDiscipline($sport, primary: true);

    $filtered = [
        'screen' => 'sport',
        'sport' => 'steel-challenge',
        'family' => 'handgun',
        'near' => '1',
        'lat' => -25.7479,
        'lng' => 28.2293,
        'km' => 100,
    ];

    $this->get(route('mockups.apps', $filtered))
        ->assertOk()
        ->assertSee('Steel Saturday')
        ->assertSee('Close to me')
        ->assertSee('Within 100 km')
        ->assertDontSee('Steel Cape');

    $this->get(route('mockups.apps', [
        'screen' => 'today',
        'near' => '1',
        'lat' => -25.7479,
        'lng' => 28.2293,
        'km' => 50,
    ]))
        ->assertOk()
        ->assertSee('Steel Saturday')
        ->assertSee('Within 50 km')
        ->assertDontSee('Steel Cape');
});

it('returns not found for an unknown mockup match', function () {
    $this->get(route('mockups.match', ['slug' => 'not-a-real-match']))
        ->assertNotFound();
});

it('carries a sport follow into my shooting and the following page', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'precision-rifle')->firstOrFail();
    $event = Event::factory()->create([
        'title' => 'Kalahari Steel',
        'venue_id' => Venue::factory()->create(['province' => Province::Gauteng])->id,
    ]);
    $event->attachDiscipline($sport, primary: true);

    $this->get(route('mockups.account', ['sport' => ['precision-rifle']]))
        ->assertOk()
        ->assertSee('Coming up')
        ->assertSee('Kalahari Steel')
        ->assertSee('Manage follows');

    $this->get(route('mockups.account.following', ['sport' => ['precision-rifle']]))
        ->assertOk()
        ->assertSee('Following')
        ->assertSee('Precision Rifle')
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('Open My Shooting');
});

it('toggles the sport follow link on the sport page', function () {
    $this->seed(DisciplineSeeder::class);

    $this->get(route('mockups.sport', ['slug' => 'precision-rifle']))
        ->assertOk()
        ->assertSee('>Follow<', false)
        ->assertSee('aria-pressed="false"', false);

    $this->get(route('mockups.sport', ['slug' => 'precision-rifle', 'sport' => ['precision-rifle']]))
        ->assertOk()
        ->assertSee('Following')
        ->assertSee('aria-pressed="true"', false);
});

it('shows the club desk with the sample club and its upcoming matches', function () {
    $this->seed(DisciplineSeeder::class);

    $sport = Discipline::query()->where('slug', 'precision-rifle')->firstOrFail();
    $club = Organisation::factory()->create([
        'name' => 'Kalahari Rifle Club',
        'description' => 'Hosts precision rifle matches across the highveld and beyond, welcoming visitors year-round.',
    ]);
    $event = Event::factory()->create([
        'title' => 'Kalahari Steel',
        'host_organisation_id' => $club->id,
        'venue_id' => Venue::factory()->create(['province' => Province::Gauteng])->id,
    ]);
    $event->attachDiscipline($sport, primary: true);

    $this->get(route('mockups.manage.index'))
        ->assertOk()
        ->assertSee('Club desk')
        ->assertSee('Kalahari Rifle Club')
        ->assertSee('Profile complete')
        ->assertSee('Kalahari Steel');

    $this->get(route('mockups.manage.matches', ['club' => $club->slug]))
        ->assertOk()
        ->assertSee('Kalahari Steel')
        ->assertSee('Create match');

    $this->get(route('mockups.manage.matches.new', ['club' => $club->slug]))
        ->assertOk()
        ->assertSee('New match for Kalahari Rifle Club')
        ->assertSee('This form does not save.');

    $this->get(route('mockups.manage.profile', ['club' => $club->slug]))
        ->assertOk()
        ->assertSee('Completeness')
        ->assertSee('% complete');
});

it('points the public list-an-event action at the club desk', function () {
    $this->get(route('mockups.home'))
        ->assertOk()
        ->assertSee(route('mockups.manage.matches.new'), false)
        ->assertDontSee(route('mockups.admin.matches.edit', ['as' => 'organiser', 'new' => 1]), false);
});
