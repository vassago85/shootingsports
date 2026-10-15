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
 * Sent to every Admin / Secretary of a club when a shooter applies for
 * membership. The application itself links through to Desk.
 */
class ClubApplicationReceivedMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(public ClubMember $member) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: 'New membership application for '.$this->member->organisation->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.club-application-received',
        );
    }
}
