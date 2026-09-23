<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public User $registrant,
        public array $roles,
        public ?string $hostHint = null,
        public ?string $businessName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New registration: '.$this->registrant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.new-registration',
        );
    }
}
