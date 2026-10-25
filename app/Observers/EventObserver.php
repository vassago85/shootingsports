<?php

namespace App\Observers;

use App\Enums\EmailCategory;
use App\Enums\EventStatus;
use App\Jobs\SendPushNotification;
use App\Mail\MatchEntriesOpen;
use App\Models\Event;
use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Fans event-level changes out to every shooter who saved the event.
 *
 * Two transitions matter today:
 *
 * - {@see EventStatus::Cancelled}: a surprise cancellation is the single
 *   highest-signal event the shooter wants on their lock screen. Push
 *   only — the cancellation page itself is the follow-up.
 * - {@see EventStatus::EntriesOpen}: the "entries just opened" alert.
 *   Push + email, gated by the EmailCategory::MatchAlerts flag per
 *   recipient. Idempotent via a `entries_opened_notified_at` column on
 *   the saved_events pivot so flipping the status back and forward in
 *   admin or desk doesn't re-page the same shooter.
 *
 * Date / venue / entry-link changes are a follow-up — they need a diff
 * of previous vs new values and a copy decision that's wider than the
 * one-line transition we have today.
 */
class EventObserver
{
    /**
     * Fields that constitute a "material" change for the mobile feed's
     * change badge. A description tweak or banner swap deliberately
     * does not flip this clock — only date, venue, and status move it.
     */
    private const MATERIAL_KEYS = ['starts_at', 'ends_at', 'venue_id', 'status'];

    public function updating(Event $event): void
    {
        foreach (self::MATERIAL_KEYS as $key) {
            if ($event->isDirty($key)) {
                // Set the timestamp on the same UPDATE query — no
                // extra round-trip, no second observer firing.
                $event->last_material_change_at = now();

                return;
            }
        }
    }

    public function updated(Event $event): void
    {
        if (! $event->wasChanged('status')) {
            return;
        }

        match ($event->status) {
            EventStatus::Cancelled => $this->fanOutCancelled($event),
            EventStatus::EntriesOpen => $this->fanOutEntriesOpen($event),
            default => null,
        };
    }

    /**
     * Push-only cancellation blast to everyone who saved the match.
     */
    private function fanOutCancelled(Event $event): void
    {
        $title = 'Match cancelled';
        $body = sprintf('%s has been cancelled.', $event->title ?? 'A saved match');

        $data = [
            'type' => 'event.cancelled',
            'event_id' => $event->id,
            'event_slug' => $event->slug,
        ];

        // Chunked fan-out keeps a popular match's cancellation from
        // loading every follower into memory at once. The job itself
        // is cheap per user (one HTTP call to Expo) so parallelism is
        // fine.
        $event->savedByUsers()
            ->select('users.id')
            ->chunkById(500, function ($users) use ($title, $body, $data): void {
                foreach ($users as $user) {
                    SendPushNotification::dispatch(
                        userId: $user->id,
                        title: $title,
                        body: $body,
                        data: $data,
                    );
                }
            });
    }

    /**
     * Entries-open blast. Every saved-by user gets a push; the subset
     * who have EmailCategory::MatchAlerts switched on also gets an
     * email. The pivot timestamp blocks a re-blast if staff flip the
     * status back and forward.
     */
    private function fanOutEntriesOpen(Event $event): void
    {
        $title = 'Entries open';
        $body = sprintf('Entries just opened for %s.', $event->title ?? 'a match you saved');

        $data = [
            'type' => 'event.entries_open',
            'event_id' => $event->id,
            'event_slug' => $event->slug,
        ];

        // Query the pivot directly: we need user_ids that haven't been
        // notified yet, in chunks, without hydrating the full Event
        // relation every iteration.
        DB::table('saved_events')
            ->where('event_id', $event->id)
            ->whereNull('entries_opened_notified_at')
            ->orderBy('user_id')
            ->chunk(500, function ($pivots) use ($event, $title, $body, $data): void {
                $userIds = $pivots->pluck('user_id');

                foreach ($userIds as $userId) {
                    SendPushNotification::dispatch(
                        userId: $userId,
                        title: $title,
                        body: $body,
                        data: $data,
                    );
                }

                // Email only the users who still want match alerts in
                // their inbox. The push already landed on their lock
                // screen so a silent email skip is the right call.
                User::query()->whereIn('id', $userIds)->get()
                    ->each(function (User $user) use ($event): void {
                        if (EmailPreferences::canSend($user, EmailCategory::MatchAlerts)) {
                            Mail::to($user)->queue(new MatchEntriesOpen($user, $event));
                        }
                    });

                DB::table('saved_events')
                    ->where('event_id', $event->id)
                    ->whereIn('user_id', $userIds->all())
                    ->update(['entries_opened_notified_at' => now()]);
            });
    }
}
