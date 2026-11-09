<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\MagazineIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagazineDownloadLink extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public const int LINK_DAYS = 7;

    public function __construct(
        public MagazineIssue $issue,
        public string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->shootingSportsFrom(),
            subject: 'Download '.$this->issue->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.magazine-download-link',
            with: [
                'linkDays' => self::LINK_DAYS,
            ],
        );
    }
}
