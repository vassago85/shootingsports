<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use App\Observers\EventObserver;
use App\Support\EmailPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Entries just opened for a match you saved" alert. Dispatched from
 * {@see EventObserver} when an event's status flips to
 * EntriesOpen, in parallel with a push notification.
 *
 * Gated on EmailCategory::MatchAlerts via
 * {@see EmailPreferences::canSend()} at the dispatch site
 * — users who have match-alert email switched off get the push only,
 * which is the intended behaviour for a shooter who already keeps the
 * app on their phone.
 */
class MatchEntriesOpen extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Entries open: '.($this->event->title ?? 'a match you saved'),
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.match-entries-open',
            with: [
                'user' => $this->user,
                'event' => $this->event,
            ],
        );
    }
}
