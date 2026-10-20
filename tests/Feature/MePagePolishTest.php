<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;

/*
 * /me polish: cards must render as real cards (not bare text) and the
 * next couple of saved matches should preview inline on the My
 * calendar card so the page is useful at a glance. These tests catch
 * CSS/class drift and controller wiring regressions — they do not
 * verify computed styles, that is a browser job.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    config()->set('plans.pro_enabled', true);
});

it('renders me cards with the me-card class so the stylesheet can target them', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/me');

    $response->assertOk();
    // Each shooter card uses the class the stylesheet actually styles.
    $response->assertSee('class="me-card is-link"', escape: false);
    // The hero plan pill uses the new me-plan class, not a stock .tag.
    $response->assertSee('class="me-plan', escape: false);
});

it('previews the next saved matches inline on the My calendar card', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['name' => 'Welbekend Range', 'town' => 'Centurion']);

    $upcoming = Event::factory()->create([
        'title' => 'Spring Precision Rifle',
        'venue_id' => $venue->id,
        'starts_at' => now()->addDays(5),
    ]);

    // Past match must not appear in the preview — only upcoming are useful.
    $past = Event::factory()->create([
        'title' => 'Autumn Service Rifle',
        'venue_id' => $venue->id,
        'starts_at' => now()->subDays(5),
    ]);

    $user->savedEvents()->attach([$upcoming->id, $past->id]);

    $response = $this->actingAs($user)->get('/me');

    $response->assertOk();
    $response->assertSee('Spring Precision Rifle');
    $response->assertSee('Welbekend Range');
    $response->assertDontSee('Autumn Service Rifle');
});

it('shows an inline Upgrade link next to the Free pill for non-Pro shooters', function () {
    $user = User::factory()->create(); // Free by default.

    $response = $this->actingAs($user)->get('/me');

    $response->assertOk();
    $response->assertSee('class="me-upgrade-inline"', escape: false);
    $response->assertSee('Upgrade to Pro');
});
