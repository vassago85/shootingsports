<?php

namespace App\Support;

use App\Models\User;

class SupplierListing
{
    /**
     * Where a "list your business" link should send this visitor.
     * Guests open signup with the supplier box ticked. Signed-in people
     * who already have a listing open it. Everyone else records the
     * intent, then continues to the form (via email confirmation if needed).
     */
    public static function startUrl(): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return route('register', ['supplier' => 1]);
        }

        if ($user->isSupplier()) {
            return route('suppliers.onboard');
        }

        return route('suppliers.start');
    }
}
