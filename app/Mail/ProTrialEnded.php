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
 * "Your Pro trial has ended" confirmation. Sent by the daily
 * scheduler the morning after plan_expires_at falls past. Purpose:
 *   1. Tell the user their status changed (they're now Free).
 *   2. Reassure them that their existing attendance-log entries are
 *      preserved, even if they're over the 3-slot Free cap — they
 *      just can't add more without upgrading.
 *   3. One-click upgrade to reactivate Pro.
 *
 * Also gated on emails_trial_nudges_enabled. If the user opted out
 * they don't get this either — the plan already reverted via
 * HasPlan::isPro() checking plan_expires_at, so no email is required
 * for the actual downgrade.
 */
class ProTrialEnded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Shooting Sports Pro trial has ended',
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.pro-trial-ended',
            with: [
                'user' => $this->user,
            ],
        );
    }
}
