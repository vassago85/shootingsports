<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your Pro trial ends in 3 days" nudge. Sent by the daily trial
 * scheduler task on T-3. Only mailable that gates on the
 * emails_trial_nudges_enabled category — a user who explicitly said
 * "no trial nudges" never receives it, and the scheduler stamps the
 * user regardless so we don't recheck them tomorrow.
 *
 * Copy is deliberately not salesy: it's a factual heads-up, not a
 * pitch. The one link is to /upgrade so they can add a card if they
 * want to keep Pro. If they do nothing, they land back on Free
 * automatically when the window closes — no negative surprises.
 */
class ProTrialEndingSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Shooting Sports Pro trial ends in 3 days',
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.pro-trial-ending-soon',
            with: [
                'user' => $this->user,
                'trialEndsAt' => $this->user->plan_expires_at,
            ],
        );
    }
}
