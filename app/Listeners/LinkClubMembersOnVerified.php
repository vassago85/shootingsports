<?php

namespace App\Listeners;

use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * When a user verifies their email, link any club-member records with
 * the same email to their new account. Imported paper members and
 * applications sent before signup both arrive here.
 */
class LinkClubMembersOnVerified
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! filled($user->email)) {
            return;
        }

        ClubMember::query()
            ->whereNull('user_id')
            // Case-insensitive match: imports often arrive mixed-case
            // and the Livewire form lower-cases new rows, so we compare
            // in lower case to catch both.
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->update(['user_id' => $user->id]);
    }
}
