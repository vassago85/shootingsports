<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\PlatformSubscription;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to organisation admins/secretaries when a platform trial is
 * about to end. `daysUntilTrialEnds` is 5, 1 or 0 and drives the
 * subject line and body copy.
 */
class TrialEndingReminder extends Mailable
{
    use FromShootingSports, SerializesModels;

    public function __construct(
        public PlatformSubscription $subscription,
        public int $daysUntilTrialEnds,
        public string $addCardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: match ($this->daysUntilTrialEnds) {
                0 => 'Your ShootingSports trial ends today',
                1 => 'Your ShootingSports trial ends tomorrow',
                default => "Your ShootingSports trial ends in {$this->daysUntilTrialEnds} days",
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trial-ending-reminder',
        );
    }
}
