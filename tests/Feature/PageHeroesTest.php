<?php

use App\Filament\Pages\ManagePagePictures;
use App\Models\Setting;
use App\Models\User;
use App\Support\PageHeroes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();

    foreach (array_keys(PageHeroes::pages()) as $page) {
        Cache::forget('page.hero.url.'.$page);
    }
});

it('uses the built-in range photo on the ranges directory', function () {
    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('images/pages/ranges.jpg', false)
        ->assertSee('class="ss-public"', false);

    expect(PageHeroes::fallbackUrl('home'))->toContain('images/pages/home.jpg')
        ->and(PageHeroes::fallbackUrl('ranges'))->toContain('images/pages/ranges.jpg')
        ->and(PageHeroes::fallbackUrl('calendar'))->toContain('images/pages/sports.jpg')
        ->and(PageHeroes::fallbackUrl('clubs'))->toContain('images/pages/clubs.jpg')
        ->and(PageHeroes::fallbackUrl('sports'))->toContain('images/pages/sports.jpg')
        ->and(PageHeroes::fallbackUrl('industry'))->toContain('images/pages/industry.jpg');
});

it('uses an uploaded picture on the public page', function () {
    Storage::fake('media');
    Storage::disk('media')->put('page-heroes/ranges.jpg', 'photo');

    PageHeroes::save('ranges', 'page-heroes/ranges.jpg');

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('page-heroes/ranges.jpg', false)
        ->assertDontSee('images/pages/ranges.jpg', false);

    expect(Setting::get('page.hero.ranges'))->toBe('page-heroes/ranges.jpg');
});

it('ignores a picture path outside the page heroes folder', function () {
    PageHeroes::save('ranges', '../.env');

    expect(Setting::get('page.hero.ranges'))->toBeNull();
});

it('lets staff open the page pictures desk', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(ManagePagePictures::getUrl())
        ->assertOk()
        ->assertSee('Page pictures');
});

it('refuses the page pictures desk to anyone who is not staff', function () {
    // Phase 2.1 soft-redirect — admin panel bounces non-staff to
    // their default home instead of 403ing.
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(ManagePagePictures::getUrl())
        ->assertRedirect(route('my-calendar'));
});
