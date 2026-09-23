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
        ->assertSee('mockup-assets/hero-range.jpg', false)
        ->assertSee('class="ss-public"', false);
});

it('uses an uploaded picture on the public page', function () {
    Storage::fake('media');
    Storage::disk('media')->put('page-heroes/ranges.jpg', 'photo');

    PageHeroes::save('ranges', 'page-heroes/ranges.jpg');

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('page-heroes/ranges.jpg', false)
        ->assertDontSee('mockup-assets/hero-range.jpg', false);

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
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(ManagePagePictures::getUrl())
        ->assertForbidden();
});
