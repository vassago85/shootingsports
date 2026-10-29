<?php

namespace App\Mail;

use App\Mail\Concerns\FromShootingSports;
use App\Models\Enquiry;
use App\Support\EnquiryListingContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnquiryForwardedMail extends Mailable implements ShouldQueue
{
    use FromShootingSports, Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry, public bool $copyEnquirer = false) {}

    public function envelope(): Envelope
    {
        $about = $this->enquiry->about;
        $label = $about->title ?? $about->name ?? $this->enquiry->type->getLabel();

        return new Envelope(
            from: $this->shootingSportsFrom(),
            cc: $this->ccAddresses(),
            subject: '[Enquiry] '.$label.' — '.$this->enquiry->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.enquiry-forwarded',
            with: [
                'copyEnquirer' => $this->copyEnquirer,
            ],
        );
    }

    /**
     * CC the enquirer when staff forward the message manually, so they
     * see the same note the organiser gets. Skip the CC when it would
     * duplicate the organiser's own address.
     *
     * @return array<int, Address>
     */
    private function ccAddresses(): array
    {
        if (! $this->copyEnquirer) {
            return [];
        }

        $enquirerEmail = trim((string) $this->enquiry->email);

        if ($enquirerEmail === '') {
            return [];
        }

        $organiserEmail = EnquiryListingContact::address($this->enquiry);

        if (is_string($organiserEmail) && strcasecmp($organiserEmail, $enquirerEmail) === 0) {
            return [];
        }

        return [new Address($enquirerEmail, (string) $this->enquiry->name)];
    }
}
