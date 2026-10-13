<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\TeamEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamEntryOrganiserMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(public TeamEntry $entry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: '[Team entry] '.$this->entry->event->title.' - '.$this->entry->team_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-entry-organiser',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (blank($this->entry->proof_of_payment_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(TeamEntry::PROOF_OF_PAYMENT_DISK, $this->entry->proof_of_payment_path)
                ->as($this->entry->proof_of_payment_name ?: basename($this->entry->proof_of_payment_path)),
        ];
    }
}
