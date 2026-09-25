<?php

namespace App\Mail;

use App\Models\Claim;
use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierClaimSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Claim $claim, public Provider $provider) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Supplier claim to review: '.$this->provider->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.supplier-claim-submitted',
        );
    }
}
