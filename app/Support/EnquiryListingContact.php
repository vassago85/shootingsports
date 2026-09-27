<?php

namespace App\Support;

use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;

/**
 * Where a listing enquiry should be delivered.
 * A filled contact address means the listing receives it alone.
 * No address means staff receive it.
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
}
