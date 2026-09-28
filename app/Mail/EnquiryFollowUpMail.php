<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
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
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(
        public Enquiry $enquiry,
        public EnquiryReply $reply,
        public bool $forStaff = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: '[Enquiry] Reply from '.$this->enquiry->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.enquiry-follow-up',
        );
    }
}
