<?php

namespace App\Listeners;

use App\Mail\NewRegistrationMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class NotifyRegistrationRecipients
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $emails = collect(config('registration.notify_emails', []))
            ->filter(fn (mixed $email): bool => is_string($email) && trim($email) !== '')
            ->map(fn (string $email): string => trim($email))
            ->reject(fn (string $email): bool => strcasecmp($email, $user->email) === 0)
            ->unique(fn (string $email): string => strtolower($email))
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $roles = ['Shooter'];

        if ($user->isMdPending() || $user->is_match_director) {
            $roles[] = 'Match director';
        }

        $businessName = session('supplier.pending_business_name');
        $businessName = is_string($businessName) && trim($businessName) !== '' ? trim($businessName) : null;

        if ($businessName !== null) {
            $roles[] = 'Supplier';
        }

        $hostHint = session('md.pending_host');
        $hostHint = is_string($hostHint) && trim($hostHint) !== '' ? trim($hostHint) : null;

        foreach ($emails as $email) {
            Mail::to($email)->queue((new NewRegistrationMail(
                registrant: $user,
                roles: $roles,
                hostHint: $hostHint,
                businessName: $businessName,
            ))->afterCommit());
        }
    }
}
