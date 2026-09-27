<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Models\EnquiryReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnquiryReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enquiry $enquiry,
        public EnquiryReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        $subject = filled($this->enquiry->subject)
            ? $this->enquiry->subject
            : $this->enquiry->type->getLabel();

        return new Envelope(
            subject: 'Re: '.$subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.enquiry-reply',
        );
    }
}
