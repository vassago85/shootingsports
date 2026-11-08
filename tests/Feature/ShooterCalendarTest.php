<?php

use App\Enums\EquipmentCategory;
use App\Enums\Plan;
use App\Enums\ProfileVisibility;
use App\Livewire\SaveToCalendar;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\ShooterEquipment;
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
        ->get(route('events.show', $mine->slug))
        ->assertOk()
        ->assertSee('Add to calendar')
        ->assertSee('Save to my Shooting Sports account');

    Livewire::actingAs($user)
        ->test(SaveToCalendar::class, ['event' => $mine])
        ->call('toggle')
        ->assertSet('saved', true);

    $user->refresh();

    expect($user->calendar_slug)->toBe('jane-shooter')
        ->and($user->calendar_token)->not->toBeEmpty()
        ->and($user->savedEvents()->pluck('events.id'))->toContain($mine->id);

    $this->get(route('embed.calendar', ['shooter' => 'jane-shooter']))
        ->assertOk()
        ->assertDontSee('My Steel Sunday')
        ->assertDontSee('Someone Else Open');

    $this->get(route('embed.calendar', ['shooter' => $user->calendar_token]))
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
        ->assertSee('Reset my calendar link')
        ->assertSee(route('ical.shooter', $user->calendar_token), false)
        ->assertSee('embed/calendar?shooter='.$user->calendar_token, false)
        ->assertDontSee('embed/calendar?shooter=jane-shooter');

    $this->get(route('ical.shooter', $user->calendar_token))
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

    $this->get(route('ical.shooter', $user->calendar_token))
        ->assertOk()
        ->assertDontSee($event->title);

    $this->get('/shooters/'.$user->calendar_slug.'/calendar.ics')
        ->assertNotFound();
});

it('keeps a shooter page private until they opt in', function () {
    $event = Event::factory()->confirmed()->create(['title' => 'Private Steel']);
    $entryEvent = Event::factory()->confirmed()->create(['title' => 'Entered Cup']);
    $owner = User::factory()->create(['name' => 'Private Shooter']);
    $owner->ensureCalendarSlug();
    $owner->savedEvents()->attach($event->id);
    ShooterEquipment::query()->create([
        'user_id' => $owner->id,
        'category' => EquipmentCategory::Rifle,
        'label' => 'Whisper Rifle',
    ]);
    EventEntry::factory()->create([
        'user_id' => $owner->id,
        'event_id' => $entryEvent->id,
    ]);

    $stranger = User::factory()->create();
    $staff = User::factory()->staff()->create();

    $this->get(route('shooters.show', $owner->calendar_slug))->assertNotFound();

    $this->actingAs($stranger)
        ->get(route('shooters.show', $owner->calendar_slug))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('shooters.show', $owner->calendar_slug))
        ->assertOk()
        ->assertSee('Private Steel')
        ->assertDontSee('Whisper Rifle')
        ->assertDontSee('Entered Cup');

    $this->actingAs($staff)
        ->get(route('shooters.show', $owner->calendar_slug))
        ->assertOk()
        ->assertDontSee('Whisper Rifle')
        ->assertDontSee('Entered Cup');

    $this->actingAs($owner)
        ->post(route('my-calendar.visibility'), ['profile_visibility' => ProfileVisibility::Public->value])
        ->assertRedirect(route('my-calendar'));

    auth()->logout();

    $this->get(route('shooters.show', $owner->calendar_slug))
        ->assertOk()
        ->assertSee('Private Steel')
        ->assertDontSee('Whisper Rifle')
        ->assertDontSee('Entered Cup')
        ->assertDontSee($owner->calendar_token);
});

it('rotates the calendar link and retires the previous token', function () {
    $event = Event::factory()->confirmed()->create(['title' => 'Token Match']);
    $user = User::factory()->create();
    $user->savedEvents()->attach($event->id);
    $old = $user->calendar_token;

    $this->actingAs($user)
        ->post(route('my-calendar.reset-link'))
        ->assertRedirect(route('my-calendar'));

    $user->refresh();

    expect($user->calendar_token)->not->toBe($old);

    $this->get(route('ical.shooter', $old))->assertNotFound();

    $this->get(route('ical.shooter', $user->calendar_token))
        ->assertOk()
        ->assertSee('Token Match');
});

it('lists saved matches and the calendar link before equipment', function () {
    $user = User::factory()->create();
    $first = Event::factory()->confirmed()->create([
        'title' => 'Saved Cup One',
        'starts_at' => now()->addDays(2),
    ]);
    $second = Event::factory()->confirmed()->create([
        'title' => 'Saved Cup Two',
        'starts_at' => now()->addDays(9),
    ]);
    $user->savedEvents()->attach([$first->id, $second->id]);

    $this->actingAs($user)
        ->get(route('my-calendar'))
        ->assertOk()
        ->assertSee('2 of 3 saved')
        ->assertSeeInOrder([
            '2 of 3 saved',
            'Saved Cup One',
            'Reset my calendar link',
            'Equipment',
        ]);
});

it('shows an open saved-match count for a pro shooter', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);
    $user->savedEvents()->attach(Event::factory()->confirmed()->create()->id);

    $this->actingAs($user)
        ->get(route('my-calendar'))
        ->assertOk()
        ->assertSee('1 saved')
        ->assertDontSee('of 3 saved');
});
