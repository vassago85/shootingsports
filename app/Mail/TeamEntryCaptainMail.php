<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\TeamEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamEntryCaptainMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(public TeamEntry $entry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: 'Your team entry for '.$this->entry->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-entry-captain',
        );
    }
}
