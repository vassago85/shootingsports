<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-shot note to people who asked to hear when Pro opened.
 * The command checks email preferences before it sends this.
 */
class ProSalesOpen extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public ?User $user = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pro is open',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pro-sales-open',
            with: [
                'recipientName' => $this->recipientName,
                'user' => $this->user,
            ],
        );
    }
}
