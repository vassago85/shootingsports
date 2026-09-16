<?php

namespace App\Support;

use App\Enums\EmailCategory;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Single gatekeeper for "may we email this user in this category?"
 *
 * All non-transactional mailables MUST call EmailPreferences::canSend
 * before dispatch. Doing the check inside the Mailable itself is too
 * late — Mailable::build is only called at render time, so if we skip
 * we still have to skip the send() at the caller. Cleaner to check at
 * the dispatch site.
 *
 * Transactional emails (password reset, MD approval/rejection, enquiry
 * receipt) skip this class entirely and always go out.
 *
 * There is deliberately no "resubscribe token" — the resubscribe path
 * happens from an authenticated session on /settings/notifications.
 * The one-click unsubscribe token is fire-once-safe (idempotent) and
 * intentionally cannot be used to opt someone _in_ — that would let
 * anyone with the token quietly re-enable marketing on a user who
 * clearly asked to be left alone.
 */
final class EmailPreferences
{
    /**
     * True if the given user is allowed to receive this category of
     * mail right now. Transactional always true. Everything else
     * checks the master marketing flag first, then the category flag.
     */
    public static function canSend(User $user, EmailCategory $category): bool
    {
        // Transactional bypasses every opt-out. Password resets and
        // account-critical mail always go out.
        if ($category === EmailCategory::Transactional) {
            return true;
        }

        // A soft-deleted or unverified user is a caller-decision, not
        // ours — we don't gate on either. Marketing consent, however,
        // is our job.
        if ((bool) $user->emails_marketing_enabled === false) {
            return false;
        }

        $flag = self::flagColumnFor($category);

        return (bool) ($user->{$flag} ?? true);
    }

    /**
     * Absolute URL that hits the one-click unsubscribe route. Prefer
     * this over URL::signedRoute — the unsubscribe token IS the
     * signature. Signed routes would rotate and break old email
     * footers, defeating the whole point.
     */
    public static function unsubscribeUrl(User $user): string
    {
        return URL::route('email.unsubscribe', ['token' => $user->unsubscribe_token]);
    }

    /**
     * Absolute URL to the authenticated preferences page. Included in
     * every marketing footer alongside the unsubscribe link so users
     * who want fine-grained control (e.g. keep match alerts, drop the
     * weekly digest) don't have to nuke everything.
     */
    public static function preferencesUrl(): string
    {
        return URL::route('settings.notifications');
    }

    /**
     * Turns an EmailCategory into the users-table boolean column that
     * gates it. Kept in one place so a typo or a new category surfaces
     * as a match failure in one file, not five.
     */
    public static function flagColumnFor(EmailCategory $category): string
    {
        return match ($category) {
            EmailCategory::Transactional => throw new \LogicException('Transactional emails do not have an opt-out flag.'),
            EmailCategory::MatchAlerts => 'emails_match_alerts_enabled',
            EmailCategory::WeeklyDigest => 'emails_weekly_digest_enabled',
            EmailCategory::ProductUpdates => 'emails_product_updates_enabled',
            EmailCategory::TrialNudges => 'emails_trial_nudges_enabled',
        };
    }

    /**
     * Master switch: unsubscribe the user from all marketing categories
     * with a single audit trail entry. Idempotent — safe to call twice
     * (the second call is a no-op but leaves the original timestamp
     * untouched so we don't lose the true opt-out date).
     *
     * @param  'email_link'|'settings_page'|'admin'  $source
     */
    public static function unsubscribeFromAll(User $user, string $source): void
    {
        $update = [
            'emails_marketing_enabled' => false,
        ];

        if ($user->unsubscribed_at === null) {
            $update['unsubscribed_at'] = now();
            $update['unsubscribe_source'] = $source;
        }

        $user->forceFill($update)->save();
    }

    /**
     * Undo unsubscribeFromAll. Only reachable from the authenticated
     * preferences page — never via a token URL. Restores the master
     * flag but leaves per-category flags as the user last set them.
     */
    public static function resubscribeToAll(User $user): void
    {
        $user->forceFill([
            'emails_marketing_enabled' => true,
            'unsubscribed_at' => null,
            'unsubscribe_source' => null,
        ])->save();
    }
}
