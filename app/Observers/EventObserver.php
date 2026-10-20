<?php

namespace App\Observers;

use App\Enums\EventStatus;
use App\Jobs\SendPushNotification;
use App\Models\Event;

/**
 * Fans event-level changes out to every shooter who saved the event. Today
 * only {@see EventStatus::Cancelled} fires a push — the shooter changed
 * their plans around this match, so a surprise cancellation is the single
 * highest-signal event they want on their lock screen.
 *
 * Date / venue / entry-link changes are coming in a follow-up — they need
 * a diff of previous vs new values and a copy decision ("Match moved from
 * X to Y") that is wider than the one-line transition we have today.
 */
class EventObserver
{
    public function updated(Event $event): void
    {
        if (! $event->wasChanged('status')) {
            return;
        }

        if ($event->status !== EventStatus::Cancelled) {
            return;
        }

        $title = 'Match cancelled';
        $body = sprintf('%s has been cancelled.', $event->title ?? 'A saved match');

        $data = [
            'type' => 'event.cancelled',
            'event_id' => $event->id,
            'event_slug' => $event->slug,
        ];

        // Chunked fan-out keeps a popular match's cancellation from loading
        // every follower into memory at once. The job itself is cheap per
        // user (one HTTP call to Expo) so parallelism is fine.
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
}
