<?php

namespace App\Mail;

use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierListingSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Provider $provider) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Supplier listing to review: '.$this->provider->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.supplier-listing-submitted',
        );
    }
}
