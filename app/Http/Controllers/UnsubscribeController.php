<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public unsubscribe endpoints. Both routes are token-authenticated
 * (no session required) so they work from any email client, including
 * Gmail's "block sender / unsubscribe" gesture which uses the
 * List-Unsubscribe header we set on marketing sends.
 *
 * The GET route is designed to be idempotent — a mail client that
 * pre-fetches links for spam scoring won't accidentally subscribe you
 * back, and clicking twice is safe. We do the actual toggle on GET
 * (not POST) because most inline "unsubscribe" links land on a GET
 * and users expect the outcome to be immediate, not "click a button
 * on the next page". This matches how every major SaaS does it.
 *
 * The POST route (resubscribe) is only reachable from the confirmation
 * page — it lets a user who unsubscribed by accident undo without
 * hunting for the settings page.
 */
class UnsubscribeController extends Controller
{
    /**
     * One-click unsubscribe. Flips the master marketing flag off and
     * shows a confirmation page with a resubscribe button and a link
     * to /settings/notifications for granular control.
     */
    public function show(string $token): View
    {
        $user = User::findByUnsubscribeToken($token);

        if (! $user instanceof User) {
            // Do NOT 404 — that leaks which tokens exist. A generic
            // "this link is invalid or has already been used" page is
            // safer, and matches the behaviour of every big-name email
            // preferences page.
            return view('public.email.unsubscribe-invalid');
        }

        // Idempotent: only flips + stamps on first hit. Subsequent
        // hits still land on the "you're unsubscribed" page but do not
        // overwrite the original unsubscribed_at.
        if ((bool) $user->emails_marketing_enabled === true) {
            EmailPreferences::unsubscribeFromAll($user, 'email_link');
            $user->refresh();
        }

        return view('public.email.unsubscribed', ['user' => $user]);
    }

    /**
     * Resubscribe via token. Reachable only from the unsubscribe
     * confirmation page — never linked from an email. See the
     * EmailPreferences::resubscribeToAll docblock for why token
     * resubscribe is a bad idea if it were accessible from an email
     * link (an attacker with the token could turn marketing back on).
     * This POST is CSRF-protected by the standard web middleware.
     */
    public function resubscribe(string $token): RedirectResponse
    {
        $user = User::findByUnsubscribeToken($token);

        if (! $user instanceof User) {
            return redirect()->route('home')->with('status', 'That link is invalid or has already been used.');
        }

        EmailPreferences::resubscribeToAll($user);

        return redirect()
            ->route('email.unsubscribe', ['token' => $token])
            ->with('status', 'You are back on the marketing list. You can fine-tune what you receive from Notifications in your account.');
    }
}
