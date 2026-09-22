<?php

namespace App\Enums;

/**
 * Categories of email we send. Every mailable declares which category
 * it belongs to, and EmailPreferences::canSend gates dispatch on the
 * matching user flag.
 *
 * Transactional is special: it's the "we legally must send this"
 * category (password resets, MD approval/rejection, receipt after a
 * charge, enquiry confirmations) and ignores every opt-out flag. Only
 * the four marketing-adjacent categories can be silenced by the user.
 *
 * Adding a new category:
 *   1. Add the case here.
 *   2. Add the matching users.emails_<key>_enabled column via a
 *      migration and cast it as boolean on the User model.
 *   3. Add the row to the /settings/notifications view.
 *   4. Update EmailPreferences::flagColumnFor().
 */
enum EmailCategory: string
{
    case Transactional = 'transactional';
    case MatchAlerts = 'match_alerts';
    case WeeklyDigest = 'weekly_digest';
    case ProductUpdates = 'product_updates';
    case TrialNudges = 'trial_nudges';

    public function label(): string
    {
        return match ($this) {
            self::Transactional => 'Account & operational',
            self::MatchAlerts => 'New match alerts',
            self::WeeklyDigest => 'Weekly digest',
            self::ProductUpdates => 'Product updates',
            self::TrialNudges => 'Trial reminders',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Transactional => 'Password resets, receipts, MD application updates, enquiry confirmations. You cannot opt out of these. They are legally required for the service to work.',
            self::MatchAlerts => 'Emails when a match matching one of your saved searches is posted, or when a club / discipline you follow adds a new fixture.',
            self::WeeklyDigest => 'A Monday summary of matches coming up in the disciplines and provinces you follow. Off by default.',
            self::ProductUpdates => 'Occasional emails when we launch a new feature or make a big change to the site. Never more than one a month.',
            self::TrialNudges => 'Two-message set during your 30-day Pro trial: one three days before it ends, one the day it ends. No other trial emails.',
        };
    }

    /**
     * True if the user is allowed to silence this category. Only
     * transactional is locked on.
     */
    public function isOptional(): bool
    {
        return $this !== self::Transactional;
    }
}
