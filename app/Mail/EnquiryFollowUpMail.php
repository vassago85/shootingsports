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

class EnquiryFollowUpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enquiry $enquiry,
        public EnquiryReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Enquiry] Reply from '.$this->enquiry->name,
            replyTo: [$this->enquiry->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.enquiry-follow-up',
        );
    }
}
