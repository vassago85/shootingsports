<?php

use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\VerificationState;
use App\Models\Organisation;
use App\Models\Provider;
use App\Support\EventDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    // UX audit #12 gates several Industry surfaces on the directory-
    // populated cache; flush between tests so seeding takes effect.
    Cache::flush();
});

/**
 * Seed just enough published Provider rows to push
 * Provider::isDirectoryPopulated() over its default threshold.
 */
function seedPopulatedIndustry(int $count = 5): void
{
    Provider::factory()->count($count)->create([
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);
}

it('renders the new tagline on the home hero', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Find your', false)
        ->assertSee('<em>sport</em>', false)
        ->assertSee('<em>club</em>', false)
        ->assertSee('<em>match</em>', false)
        ->assertDontSee('The national register of South African shooting sport. Clubs, ranges, suppliers');
});

it('emits the tagline as the default meta description', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('name="description" content="Find your sport. Find your club. Find your match.', false);
});

it('labels the disciplines nav link as Discover', function () {
    $response = $this->get(route('home'))->assertOk();

    // Nav link points at the existing /disciplines URL — we only
    // change the visible label, not the route.
    $html = $response->getContent();

    expect($html)->toContain('href="'.route('disciplines.index').'">Discover</a>');
});

it('labels the suppliers footer link as Industry', function () {
    $response = $this->get(route('home'))->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('href="'.route('suppliers.index').'">Industry</a>');
});

it('renames the home directory column to Industry with the new blurb (once populated)', function () {
    seedPopulatedIndustry();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<h3>Industry</h3>', false)
        ->assertSee('See the industry →', false)
        ->assertDontSee('<h3>Suppliers</h3>', false);
});

it('keeps Industry out of the home stats bar even when the directory is populated', function () {
    // Bundle A #4: traction stats only. Industry lives in the directory
    // column (when populated), not in the strip.
    seedPopulatedIndustry();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('<span>Industry</span>', false)
        ->assertDontSee('<span>Suppliers</span>', false)
        ->assertSee('<span>Clubs &amp; series</span>', false);
});

it('rebrands the /suppliers page as Industry', function () {
    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('<h1>Industry</h1>', false)
        ->assertDontSee('<h1>Suppliers</h1>', false);
});

it('rebrands the /disciplines page as Discover', function () {
    $this->get(route('disciplines.index'))
        ->assertOk()
        ->assertSee('<h1>Discover</h1>', false)
        ->assertDontSee('<h1>Disciplines</h1>', false)
        ->assertDontSee('noindexed, not deleted');
});

it('rewrites the /clubs intro without leaking the data-model note', function () {
    $this->get(route('clubs.index'))
        ->assertOk()
        ->assertSee('Find your club', false)
        ->assertDontSee('Home province is for orientation. The range lives on the match');
});

it('EventDate::short renders "Sat 19 Sep" without a year in the current calendar year', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 15, 12, 0, 0, 'Africa/Johannesburg'));

    $starts = Carbon::create(2026, 9, 19, 8, 0, 0, 'Africa/Johannesburg');

    expect(EventDate::weekday($starts))->toBe('Sat')
        ->and(EventDate::dayOfMonth($starts))->toBe('19')
        ->and(EventDate::monthWithYear($starts))->toBe('Sep')
        ->and(EventDate::short($starts))->toBe('Sat 19 Sep');

    Carbon::setTestNow();
});

it('EventDate::short includes the year when the date rolls into a new calendar year', function () {
    Carbon::setTestNow(Carbon::create(2026, 12, 20, 12, 0, 0, 'Africa/Johannesburg'));

    $starts = Carbon::create(2027, 1, 10, 8, 0, 0, 'Africa/Johannesburg');

    expect(EventDate::monthWithYear($starts))->toBe('Jan 27')
        ->and(EventDate::short($starts))->toBe('Sun 10 Jan 27');

    Carbon::setTestNow();
});

it('verification badge renders nothing when the listing is unconfirmed', function () {
    $listing = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'verification_state' => VerificationState::Unconfirmed,
        'last_verified_at' => null,
    ]);

    $html = view('components.verification-badge', ['listing' => $listing])->render();

    expect(trim($html))->toBe('')
        ->and($html)->not->toContain('Unconfirmed')
        ->and($html)->not->toContain('badge-verified');
});

it('verification badge renders Confirmed with the earned-state variant class', function () {
    $listing = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'verification_state' => VerificationState::Verified,
        'last_verified_at' => Carbon::create(2026, 8, 1),
    ]);

    $html = view('components.verification-badge', ['listing' => $listing])->render();

    expect($html)
        ->toContain('badge-verified--verified')
        ->toContain('Confirmed August 2026')
        ->not->toContain('badge-verified--unconfirmed');
});

it('hide-when-vacant silences the ad slot when no placements exist', function () {
    // The home page uses hide-when-vacant. With no placements seeded,
    // the leaderboard "Advertise here" pitch must not render — the
    // reviewer flagged that block above the first match as thin.
    $response = $this->get(route('home'))->assertOk();

    expect($response->getContent())
        ->not->toContain('This space is available')
        ->not->toContain('ad-vacant');
});

it('hides the vacant ad-slot pitch on the calendar, ranges and suppliers pages', function () {
    // UX audit #13: the vacant "Advertise here" pitch above the
    // filters read as thin. Calendar, ranges and suppliers all now
    // pass hide-when-vacant so an unsold slot renders nothing —
    // the actual /advertise sales page still carries the rate card.
    foreach ([route('calendar'), route('ranges.index'), route('suppliers.index')] as $url) {
        $response = $this->get($url)->assertOk();

        expect($response->getContent())
            ->not->toContain('This space is available')
            ->not->toContain('ad-vacant');
    }
});
