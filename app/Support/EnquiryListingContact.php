<?php

namespace App\Support;

use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Where a listing enquiry should be delivered.
 * The listing receives a copy when it has a contact address.
 * Shooting Sports staff always receive their own copy.
 */
final class EnquiryListingContact
{
    public static function address(Enquiry $enquiry): ?string
    {
        $about = $enquiry->about;

        $email = match (true) {
            $about instanceof Organisation => $about->email,
            $about instanceof Provider => $about->email,
            $about instanceof Event => $about->contact_email ?: $about->hostOrganisation?->email,
            default => null,
        };

        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);

        return $email !== '' ? $email : null;
    }

    /**
     * Send the listing copy when there is a contact address, and always
     * send the staff copy so the enquiry stays with Shooting Sports.
     */
    public static function deliver(Enquiry $enquiry, Mailable $listingMail, Mailable $staffMail): void
    {
        $listingEmail = self::address($enquiry);

        if ($listingEmail !== null) {
            Mail::to($listingEmail)->queue(clone $listingMail);
        }

        $staffEmails = User::query()
            ->where('is_staff', true)
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($staffEmails as $email) {
            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            if ($listingEmail !== null && strcasecmp($email, $listingEmail) === 0) {
                continue;
            }

            Mail::to($email)->queue(clone $staffMail);
        }
    }
}
