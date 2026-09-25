<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class StaffInbox
{
    /**
     * Queue one copy of a mailable to each registration notice address.
     * The applicant's own address is left out so they do not receive the staff copy.
     */
    public static function queue(Mailable $mail, ?string $exceptEmail = null): void
    {
        $emails = collect(config('registration.notify_emails', []))
            ->filter(fn (mixed $email): bool => is_string($email) && trim($email) !== '')
            ->map(fn (string $email): string => trim($email))
            ->reject(fn (string $email): bool => $exceptEmail !== null && strcasecmp($email, $exceptEmail) === 0)
            ->unique(fn (string $email): string => strtolower($email))
            ->values();

        foreach ($emails as $email) {
            Mail::to($email)->queue((clone $mail)->afterCommit());
        }
    }
}
