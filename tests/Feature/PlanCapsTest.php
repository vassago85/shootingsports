<?php

use App\Enums\Plan;
use App\Exceptions\PlanLimitExceeded;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\SavedSearch;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('blocks a free user from creating a 4th follow via direct model create', function () {
    $user = User::factory()->create();
    $orgs = Organisation::factory()->count(4)->create();

    // First 3 succeed
    foreach ($orgs->take(3) as $org) {
        Follow::create([
            'user_id' => $user->id,
            'followable_type' => 'organisation',
            'followable_id' => $org->id,
        ]);
    }

    expect(Follow::query()->where('user_id', $user->id)->count())->toBe(3);

    // 4th throws — server-side observer
    expect(fn () => Follow::create([
        'user_id' => $user->id,
        'followable_type' => 'organisation',
        'followable_id' => $orgs->last()->id,
    ]))->toThrow(PlanLimitExceeded::class);

    expect(Follow::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('lets a pro user create as many follows as they want', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $orgs = Organisation::factory()->count(10)->create();

    foreach ($orgs as $org) {
        Follow::create([
            'user_id' => $user->id,
            'followable_type' => 'organisation',
            'followable_id' => $org->id,
        ]);
    }

    expect(Follow::query()->where('user_id', $user->id)->count())->toBe(10);
});

it('degrades an expired pro user to the free follow cap', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->subDay(),
    ]);
    $orgs = Organisation::factory()->count(4)->create();

    foreach ($orgs->take(3) as $org) {
        Follow::create([
            'user_id' => $user->id,
            'followable_type' => 'organisation',
            'followable_id' => $org->id,
        ]);
    }

    expect(fn () => Follow::create([
        'user_id' => $user->id,
        'followable_type' => 'organisation',
        'followable_id' => $orgs->last()->id,
    ]))->toThrow(PlanLimitExceeded::class);
});

it('blocks a free user from creating a 2nd saved search', function () {
    $user = User::factory()->create();

    SavedSearch::create([
        'user_id' => $user->id,
        'name' => 'First',
        'params' => ['family' => 'rifle'],
    ]);

    expect(fn () => SavedSearch::create([
        'user_id' => $user->id,
        'name' => 'Second',
        'params' => ['family' => 'handgun'],
    ]))->toThrow(PlanLimitExceeded::class);

    expect(SavedSearch::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('lets a pro user create multiple saved searches', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    for ($i = 0; $i < 5; $i++) {
        SavedSearch::create([
            'user_id' => $user->id,
            'name' => "Search {$i}",
            'params' => ['family' => 'rifle'],
        ]);
    }

    expect(SavedSearch::query()->where('user_id', $user->id)->count())->toBe(5);
});

it('exposes the trigger on the exception so UpgradePrompt can key off it', function () {
    $user = User::factory()->create();

    SavedSearch::create([
        'user_id' => $user->id,
        'name' => 'First',
        'params' => [],
    ]);

    try {
        SavedSearch::create([
            'user_id' => $user->id,
            'name' => 'Second',
            'params' => [],
        ]);
        $this->fail('Expected PlanLimitExceeded not thrown');
    } catch (PlanLimitExceeded $e) {
        expect($e->trigger)->toBe('saved_search_limit')
            ->and($e->limitKey)->toBe('saved_searches')
            ->and($e->current)->toBe(1);
    }
});

it('my-calendar clips past matches beyond the free history window for free users', function () {
    $user = User::factory()->create(); // free = 12 months
    $user->ensureCalendarSlug();

    // Recent past — inside the 12-month window
    $recent = Event::factory()->create([
        'starts_at' => now()->subMonths(3),
    ]);

    // Long past — outside the window
    $old = Event::factory()->create([
        'starts_at' => now()->subMonths(24),
    ]);

    $user->savedEvents()->syncWithoutDetaching([$recent->id, $old->id]);

    $response = $this->actingAs($user)->get(route('my-calendar'))->assertOk();

    $response->assertSee('Past')
        ->assertSee($recent->title)
        ->assertDontSee($old->title)
        ->assertSee('Earlier matches are in your Pro history');
});

it('my-calendar shows every past match for pro users', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $user->ensureCalendarSlug();

    $recent = Event::factory()->create([
        'starts_at' => now()->subMonths(3),
    ]);
    $old = Event::factory()->create([
        'starts_at' => now()->subMonths(24),
    ]);

    $user->savedEvents()->syncWithoutDetaching([$recent->id, $old->id]);

    $this->actingAs($user)->get(route('my-calendar'))
        ->assertOk()
        ->assertSee($recent->title)
        ->assertSee($old->title)
        ->assertDontSee('Earlier matches are in your Pro history');
});

it('my-calendar does not render the cut-off row when nothing is clipped', function () {
    $user = User::factory()->create();
    $user->ensureCalendarSlug();

    $recent = Event::factory()->create([
        'starts_at' => now()->subMonths(3),
    ]);

    $user->savedEvents()->syncWithoutDetaching([$recent->id]);

    $this->actingAs($user)->get(route('my-calendar'))
        ->assertOk()
        ->assertSee($recent->title)
        ->assertDontSee('Earlier matches are in your Pro history');
});
