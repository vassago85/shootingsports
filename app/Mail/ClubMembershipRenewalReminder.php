<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\ClubMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a club member ahead of their membership expiring. The
 * `daysUntilExpiry` value is the reminder threshold that triggered
 * this send (30, 14 or 7), not the exact day count — that lets the
 * email copy stay stable across clock-skew and weekend scheduler
 * misses.
 */
class ClubMembershipRenewalReminder extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(
        public ClubMember $member,
        public int $daysUntilExpiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: 'Your '.$this->member->organisation->name.' membership expires soon',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.club-membership-renewal-reminder',
        );
    }
}
