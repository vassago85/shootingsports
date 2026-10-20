<?php

use App\Enums\EventStatus;
use App\Jobs\SendPushNotification;
use App\Mail\MatchEntriesOpen;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * Entry Alerts: when a saved match flips to EntriesOpen, every shooter
 * who saved it gets a push, and the subset who still want match-alert
 * email gets an email too. Idempotent per shooter per event via the
 * saved_events.entries_opened_notified_at pivot column.
 */

beforeEach(function (): void {
    config()->set('coming-soon.enabled', false);
});

it('fans a push out to every saver when a saved match flips to entries open', function () {
    Bus::fake([SendPushNotification::class]);
    Mail::fake();

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);

    $a = User::factory()->create();
    $b = User::factory()->create();
    $notSaving = User::factory()->create();

    $a->savedEvents()->attach($event);
    $b->savedEvents()->attach($event);

    $event->forceFill(['status' => EventStatus::EntriesOpen])->save();

    Bus::assertDispatchedTimes(SendPushNotification::class, 2);
    Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $a->id
        && $job->data['type'] === 'event.entries_open'
        && $job->data['event_id'] === $event->id);
    Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $b->id);
    Bus::assertNotDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $notSaving->id);
});

it('emails savers who have match alerts enabled and skips those who have switched them off', function () {
    Bus::fake([SendPushNotification::class]);
    Mail::fake();

    $event = Event::factory()->create(['status' => EventStatus::Confirmed, 'title' => 'Spring PRS']);

    $wantsEmail = User::factory()->create([
        'emails_marketing_enabled' => true,
        'emails_match_alerts_enabled' => true,
    ]);
    $pushOnly = User::factory()->create([
        'emails_marketing_enabled' => true,
        'emails_match_alerts_enabled' => false,
    ]);
    $unsubscribed = User::factory()->create([
        'emails_marketing_enabled' => false,
    ]);

    $wantsEmail->savedEvents()->attach($event);
    $pushOnly->savedEvents()->attach($event);
    $unsubscribed->savedEvents()->attach($event);

    $event->forceFill(['status' => EventStatus::EntriesOpen])->save();

    // Three pushes, one email.
    Bus::assertDispatchedTimes(SendPushNotification::class, 3);
    Mail::assertQueued(MatchEntriesOpen::class, 1);
    Mail::assertQueued(MatchEntriesOpen::class, fn (MatchEntriesOpen $mail): bool => $mail->hasTo($wantsEmail->email));
    Mail::assertNotQueued(MatchEntriesOpen::class, fn (MatchEntriesOpen $mail): bool => $mail->hasTo($pushOnly->email) || $mail->hasTo($unsubscribed->email));
});

it('does not re-notify when the status flips back and forward', function () {
    Bus::fake([SendPushNotification::class]);
    Mail::fake();

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);
    $saver = User::factory()->create([
        'emails_marketing_enabled' => true,
        'emails_match_alerts_enabled' => true,
    ]);
    $saver->savedEvents()->attach($event);

    $event->forceFill(['status' => EventStatus::EntriesOpen])->save();
    $event->refresh()->forceFill(['status' => EventStatus::Planned])->save();
    $event->refresh()->forceFill(['status' => EventStatus::EntriesOpen])->save();

    // One push and one email, not three.
    Bus::assertDispatchedTimes(SendPushNotification::class, 1);
    Mail::assertQueued(MatchEntriesOpen::class, 1);

    $pivot = DB::table('saved_events')
        ->where('user_id', $saver->id)
        ->where('event_id', $event->id)
        ->first();
    expect($pivot->entries_opened_notified_at)->not->toBeNull();
});

it('notifies a brand-new saver on the next entries-open transition even if other savers were already notified', function () {
    Bus::fake([SendPushNotification::class]);
    Mail::fake();

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);
    $early = User::factory()->create();
    $early->savedEvents()->attach($event);

    $event->forceFill(['status' => EventStatus::EntriesOpen])->save();
    Bus::assertDispatchedTimes(SendPushNotification::class, 1);

    // Status moves off, a late saver joins, status flips back on.
    $event->refresh()->forceFill(['status' => EventStatus::Planned])->save();
    $late = User::factory()->create();
    $late->savedEvents()->attach($event);
    $event->refresh()->forceFill(['status' => EventStatus::EntriesOpen])->save();

    // Early saver was already paged, late saver gets the single new push.
    Bus::assertDispatchedTimes(SendPushNotification::class, 2);
    Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $late->id);
});
