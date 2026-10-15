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
 * Sent to the applicant when a club approves or rejects their
 * application. On reject the club's reason is included verbatim.
 */
class ClubApplicationDecisionMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(
        public ClubMember $member,
        public bool $approved,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $outcome = $this->approved ? 'approved' : 'not approved';

        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: 'Your '.$this->member->organisation->name.' membership application was '.$outcome,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.club-application-decision',
        );
    }
}
