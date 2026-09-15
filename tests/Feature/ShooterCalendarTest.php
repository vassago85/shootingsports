<?php

use App\Livewire\SaveToCalendar;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('lets a signed-in shooter pin matches and embed only those', function () {
    $mine = Event::factory()->confirmed()->create(['title' => 'My Steel Sunday']);
    $other = Event::factory()->confirmed()->create(['title' => 'Someone Else Open']);
    $user = User::factory()->create(['name' => 'Jane Shooter']);

    $this->actingAs($user)
        ->get(route('matches.show', $mine->slug))
        ->assertOk()
        ->assertSee('Add to my calendar');

    Livewire::actingAs($user)
        ->test(SaveToCalendar::class, ['event' => $mine])
        ->call('toggle')
        ->assertSet('saved', true);

    $user->refresh();

    expect($user->calendar_slug)->toBe('jane-shooter')
        ->and($user->savedEvents()->pluck('events.id'))->toContain($mine->id);

    $this->get(route('embed.calendar', ['shooter' => 'jane-shooter']))
        ->assertOk()
        ->assertSee('Jane Shooter calendar')
        ->assertSee('My Steel Sunday')
        ->assertDontSee('Someone Else Open');

    $this->actingAs($user)
        ->get(route('my-calendar'))
        ->assertOk()
        ->assertSee('My Steel Sunday')
        ->assertSee('Subscribe on your phone')
        ->assertSee('Add to Google Calendar')
        ->assertSee(route('ical.shooter', 'jane-shooter'), false)
        ->assertSee('embed/calendar?shooter=jane-shooter', false);

    $this->get(route('shooters.show', 'jane-shooter'))
        ->assertOk()
        ->assertSee('My Steel Sunday');

    $this->get(route('ical.shooter', 'jane-shooter'))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8')
        ->assertSee('My Steel Sunday')
        ->assertSee('REFRESH-INTERVAL')
        ->assertSee('UID:event-'.$mine->id.'@shootingsports.co.za');
});

it('does not show a missing shooter calendar as the nationwide feed', function () {
    Event::factory()->confirmed()->create(['title' => 'Nationwide Open']);

    $this->get(route('embed.calendar', ['shooter' => 'no-such-shooter']))
        ->assertOk()
        ->assertSee('No upcoming matches')
        ->assertDontSee('Nationwide Open');

    $this->get(route('shooters.show', 'no-such-shooter'))
        ->assertNotFound();
});

it('sends guests to the public login when they hit my-calendar', function () {
    $this->get(route('my-calendar'))
        ->assertRedirect(route('login'));
});

it('lets a shooter take a match off their calendar', function () {
    $event = Event::factory()->confirmed()->create();
    $user = User::factory()->create();
    $user->ensureCalendarSlug();
    $user->savedEvents()->attach($event->id);

    Livewire::actingAs($user)
        ->test(SaveToCalendar::class, ['event' => $event])
        ->assertSet('saved', true)
        ->call('toggle')
        ->assertSet('saved', false);

    expect($user->savedEvents()->count())->toBe(0);

    $this->get(route('ical.shooter', $user->calendar_slug))
        ->assertOk()
        ->assertDontSee($event->title);
});
