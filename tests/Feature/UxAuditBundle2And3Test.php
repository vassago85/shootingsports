<?php

use App\Enums\DisciplineFamily;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Livewire\CalendarFilter;
use App\Livewire\MatchFinder;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

// ---- Bundle 2 -----------------------------------------------------

// #1 Radius killed
it('hero MatchFinder no longer exposes a radius dropdown', function () {
    Livewire::test(MatchFinder::class)
        ->assertDontSee('Within')
        ->assertDontSee('Any distance')
        ->assertDontSee('50 km');
});

it('MatchFinder submit URL no longer includes ?radius', function () {
    Livewire::test(MatchFinder::class)
        ->set('discipline', 'ipsc-handgun')
        ->set('province', 'gauteng')
        ->call('search')
        ->assertRedirect(route('calendar', [
            'discipline' => 'ipsc-handgun',
            'province' => 'gauteng',
        ]));
});

it('MatchFinder no longer has a $radius property', function () {
    expect(property_exists(MatchFinder::class, 'radius'))->toBeFalse();
});

// #2 + #14 Chip state + result count + clear filters
it('CalendarFilter renders an active-filter chip when discipline URL param is set', function () {
    Discipline::factory()->create(['slug' => 'ipsc-handgun', 'name' => 'IPSC Handgun', 'is_published' => true]);

    Livewire::test(CalendarFilter::class, ['discipline' => 'ipsc-handgun'])
        ->assertSee('IPSC Handgun')
        ->assertSee('Filtered:');
});

it('CalendarFilter hasActiveFilters is false on defaults, true on any filter', function () {
    $c = Livewire::test(CalendarFilter::class);

    expect($c->instance()->hasActiveFilters())->toBeFalse();

    $c->set('family', 'rifle');
    expect($c->instance()->hasActiveFilters())->toBeTrue();

    $c->set('family', 'all')->set('confirmed', true);
    expect($c->instance()->hasActiveFilters())->toBeTrue();
});

it('CalendarFilter clearAll resets every filter to default', function () {
    Livewire::test(CalendarFilter::class)
        ->set('family', 'rifle')
        ->set('novice', true)
        ->set('confirmed', true)
        ->set('discipline', 'ipsc-handgun')
        ->set('province', 'gauteng')
        ->set('from', '2026-01-01')
        ->set('to', '2026-12-31')
        ->call('clearAll')
        ->assertSet('family', 'all')
        ->assertSet('novice', false)
        ->assertSet('confirmed', false)
        ->assertSet('discipline', null)
        ->assertSet('province', null)
        ->assertSet('from', null)
        ->assertSet('to', null);
});

it('CalendarFilter renders singular "1 match" when result count is 1', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    Event::factory()->create([
        'host_organisation_id' => $host->id,
        'status' => 'confirmed',
        'starts_at' => now()->addDays(3),
    ]);

    Livewire::test(CalendarFilter::class)
        ->assertSee('1 match')
        ->assertDontSee('1 matches');
});

it('CalendarFilter renders plural "N matches" when count > 1', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    Event::factory()->count(3)->create([
        'host_organisation_id' => $host->id,
        'status' => 'confirmed',
        'starts_at' => now()->addDays(3),
    ]);

    Livewire::test(CalendarFilter::class)
        ->assertSee('3 matches');
});

it('CalendarFilter shows Clear filters button only when a filter is active', function () {
    Livewire::test(CalendarFilter::class)
        ->assertDontSee('Clear filters')
        ->set('family', 'rifle')
        ->assertSee('Clear filters');
});

// ---- Bundle 3 -----------------------------------------------------

// #11 Discover sort by upcoming count
it('Discover index sorts disciplines by upcoming count desc then name', function () {
    // Three disciplines with different upcoming counts. The one with
    // the most upcoming matches should render first.
    $a = Discipline::factory()->create(['name' => 'Alpha', 'family' => DisciplineFamily::Rifle, 'is_published' => true, 'parent_id' => null]);
    $b = Discipline::factory()->create(['name' => 'Bravo', 'family' => DisciplineFamily::Handgun, 'is_published' => true, 'parent_id' => null]);
    $c = Discipline::factory()->create(['name' => 'Charlie', 'family' => DisciplineFamily::Shotgun, 'is_published' => true, 'parent_id' => null]);

    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);

    // Bravo gets 3 events, Alpha gets 1, Charlie gets 0.
    Event::factory()->count(3)->confirmed()->create([
        'host_organisation_id' => $host->id,
        'starts_at' => now()->addDays(5),
    ])->each(fn ($e) => $e->disciplines()->attach($b));

    Event::factory()->create([
        'host_organisation_id' => $host->id,
        'status' => 'confirmed',
        'starts_at' => now()->addDays(5),
    ])->disciplines()->attach($a);

    $response = $this->get(route('disciplines.index'));

    $html = $response->getContent();
    $bPos = strpos($html, 'Bravo');
    $aPos = strpos($html, 'Alpha');
    $cPos = strpos($html, 'Charlie');

    expect($bPos)->toBeLessThan($aPos)   // 3 > 1
        ->and($aPos)->toBeLessThan($cPos); // 1 > 0
});

it('Discover renders "No matches listed yet" copy on zero-upcoming tiles', function () {
    Discipline::factory()->create(['name' => 'Empty Sport', 'is_published' => true, 'parent_id' => null]);

    $this->get(route('disciplines.index'))
        ->assertSee('Empty Sport')
        ->assertSee('No matches listed yet');
});

// #12 Industry hidden below threshold
it('layout hides the Industry footer link when fewer than 5 providers exist', function () {
    // Under threshold — 3 published providers.
    Provider::factory()->count(3)->create(['status' => ListingStatus::Published, 'category' => ProviderCategory::Dealer]);

    $html = $this->get(route('home'))->getContent();

    // The footer should not have the Industry link visible.
    // We check that the specific route('suppliers.index') anchor with
    // "Industry" label is absent — clubs/ranges links remain.
    expect($html)->not->toContain('>Industry</a>');
});

it('layout shows the Industry footer link once 5+ providers exist', function () {
    Provider::factory()->count(5)->create(['status' => ListingStatus::Published, 'category' => ProviderCategory::Dealer]);

    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('>Industry</a>');
});

it('homepage hides the "Industry" hero stat when directory is not populated', function () {
    // Zero providers.
    $html = $this->get(route('home'))->getContent();

    // Hero stats block should show Clubs/Series/Ranges/Matches/Disciplines/Provinces
    // but NOT the "Industry" chip.
    expect($html)->toContain('<span>Clubs</span>')
        ->and($html)->not->toContain('<span>Industry</span>');
});

// #13 Ad slot moves + hide-when-vacant
it('the calendar page renders the ad-slot below the Livewire filter component with hide-when-vacant', function () {
    $view = file_get_contents(resource_path('views/public/calendar.blade.php'));

    // Ad slot is positioned AFTER the livewire component and gates on
    // hide-when-vacant so a vacant slot renders nothing above results.
    $liveWirePos = strpos($view, '<livewire:calendar-filter');
    $adPos = strpos($view, '<x-ad-slot');

    expect($liveWirePos)->toBeInt()
        ->and($adPos)->toBeInt()
        ->and($adPos)->toBeGreaterThan($liveWirePos)
        ->and($view)->toContain('hide-when-vacant');
});

it('the ranges page renders the ad-slot with hide-when-vacant', function () {
    $view = file_get_contents(resource_path('views/public/ranges/index.blade.php'));

    expect($view)->toContain('hide-when-vacant');
});

it('the suppliers page renders the ad-slot with hide-when-vacant', function () {
    $view = file_get_contents(resource_path('views/public/suppliers/index.blade.php'));

    expect($view)->toContain('hide-when-vacant');
});

// ---- Regression sanity --------------------------------------------

it('CalendarFilter still passes a bookmarked ?radius= URL param through without error', function () {
    // Bookmarked URLs with the deprecated radius param should not
    // break the page — CalendarFilter still accepts the property so
    // pre-existing bookmarks continue to work (radius silently no-ops
    // because PublicEventQuery needs a single province + venue coords).
    Livewire::test(CalendarFilter::class, ['radius' => '150'])
        ->assertSet('radius', '150')
        ->assertOk();
});
