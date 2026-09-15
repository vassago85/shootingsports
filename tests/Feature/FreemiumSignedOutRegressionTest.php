<?php

use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;

/**
 * Freemium foundation regression guard.
 *
 * Every publicly-browsable surface must stay 200 + full-content for a
 * signed-out visitor. The freemium caps are personalisation limits;
 * they must never gate access to the calendar, iCal feeds, embed, or
 * a match page. If any of these ever require auth, that is a bug and
 * this test catches it before it ships.
 */
beforeEach(function () {
    $this->withoutVite();
});

it('the public /calendar renders for guests and shows upcoming matches', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addDays(14),
    ]);

    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee($event->title);
});

it('a discipline iCal feed is available to guests', function () {
    $discipline = Discipline::query()->where('is_published', true)->first()
        ?? Discipline::factory()->create(['is_published' => true]);

    $response = $this->get(route('ical.discipline', $discipline->slug));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toStartWith('text/calendar');
});

it('a club iCal feed is available to guests', function () {
    $organisation = Organisation::factory()->create();

    $response = $this->get(route('ical.organisation', $organisation->slug));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toStartWith('text/calendar');
});

it('the embed calendar renders for guests', function () {
    Event::factory()->create(['starts_at' => now()->addDays(7)]);

    $this->get(route('embed.calendar'))->assertOk();
});

it('a match show page renders for guests', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->addDays(10),
    ]);

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee($event->title);
});

it('the /advertise rate card renders for guests', function () {
    $this->get(route('advertise'))->assertOk();
});

it('the home page renders for guests without opening the upgrade prompt', function () {
    $response = $this->get(route('home'))->assertOk();

    // The prompt component is registered in the layout but MUST NOT
    // render its modal chrome on load. If we ever see the panel or
    // the "Notify me" copy on a fresh guest home page, that is a bug.
    $response->assertDontSee('upgrade-prompt-panel', escape: false)
        ->assertDontSee('Notify me when Pro launches');
});
