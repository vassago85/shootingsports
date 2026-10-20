<?php

use App\Models\User;

/**
 * The shooter home used to live at three top-level URLs
 * (`/my-calendar`, `/my-log`, `/settings/notifications`). Those pages
 * now live under `/me/*` so the whole shooter surface sits inside
 * one hub, and the old URLs 301 to the new ones so bookmarked and
 * emailed links keep working.
 *
 * The route **names** are deliberately unchanged (`my-calendar`,
 * `my-log`, `settings.notifications`) so every existing caller
 * resolves to the new URL with no bounce.
 */
it('301s /my-calendar to /me/calendar', function () {
    $this->get('/my-calendar')
        ->assertStatus(301)
        ->assertRedirect('/me/calendar');
});

it('301s /my-log to /me/log', function () {
    $this->get('/my-log')
        ->assertStatus(301)
        ->assertRedirect('/me/log');
});

it('301s /my-log sub-URLs to /me/log sub-URLs', function () {
    $this->get('/my-log/export/csv')
        ->assertStatus(301)
        ->assertRedirect('/me/log/export/csv');

    $this->get('/my-log/print')
        ->assertStatus(301)
        ->assertRedirect('/me/log/print');
});

it('301s /settings/notifications to /me/settings', function () {
    $this->get('/settings/notifications')
        ->assertStatus(301)
        ->assertRedirect('/me/settings');
});

it('resolves the my-calendar route name to /me/calendar', function () {
    expect(route('my-calendar', [], false))->toBe('/me/calendar');
});

it('resolves the my-log route name to /me/log', function () {
    expect(route('my-log', [], false))->toBe('/me/log');
});

it('resolves the settings.notifications route name to /me/settings', function () {
    expect(route('settings.notifications', [], false))->toBe('/me/settings');
});

it('serves the shooter calendar at the new URL', function () {
    $this->actingAs(User::factory()->create())
        ->get('/me/calendar')
        ->assertOk();
});

it('serves the shooter log at the new URL', function () {
    $this->actingAs(User::factory()->create())
        ->get('/me/log')
        ->assertOk();
});

it('serves the notification preferences at the new URL', function () {
    $this->actingAs(User::factory()->create())
        ->get('/me/settings')
        ->assertOk();
});
