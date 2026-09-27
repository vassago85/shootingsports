<?php

namespace App\Mail;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ListingClaimSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Claim $claim,
        public string $listingName,
        public string $listingKind,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ucfirst($this->listingKind).' claim to review: '.$this->listingName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.listing-claim-submitted',
        );
    }
}
