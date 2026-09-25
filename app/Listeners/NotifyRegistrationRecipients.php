<?php

namespace App\Listeners;

use App\Mail\NewRegistrationMail;
use App\Models\User;
use App\Support\StaffInbox;
use Illuminate\Auth\Events\Registered;

class NotifyRegistrationRecipients
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $roles = ['Shooter'];

        if ($user->isMdPending() || $user->is_match_director) {
            $roles[] = 'Match director';
        }

        $businessName = $user->pending_business_name ?: session('supplier.pending_business_name');
        $businessName = is_string($businessName) && trim($businessName) !== '' ? trim($businessName) : null;

        if ($businessName !== null) {
            $roles[] = 'Supplier';
        }

        $hostHint = session('md.pending_host');
        $hostHint = is_string($hostHint) && trim($hostHint) !== '' ? trim($hostHint) : null;

        StaffInbox::queue(new NewRegistrationMail(
            registrant: $user,
            roles: $roles,
            hostHint: $hostHint,
            businessName: $businessName,
        ), $user->email);
    }
}
