<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnquiryReceivedMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: '[Enquiry] '.$this->enquiry->type->getLabel().' — '.$this->enquiry->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.enquiry-received',
        );
    }
}
