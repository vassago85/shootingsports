<?php

namespace App\Support;

use App\Exceptions\PlanLimitExceeded;
use App\Models\User;

/**
 * Single choke-point for cap enforcement. Called from model observers
 * (server-side, unbypassable) and from Livewire components (so the UI
 * gets a clean UpgradePrompt trigger instead of a stack trace).
 *
 * The trigger string is the same key that UpgradePrompt renders copy
 * for — keeping them in one file means adding a new cap is a single
 * table row edit, not a hunt across three components.
 */
class EnforcesPlanLimits
{
    /**
     * Cap key => UpgradePrompt trigger.
     *
     * @var array<string, string>
     */
    public const TRIGGERS = [
        'follows' => 'follow_limit',
        'saved_searches' => 'saved_search_limit',
        'history_months' => 'history_window',
        'export' => 'export',
        'household_profiles' => 'household',
    ];

    /**
     * Throws when the current count is at or above the cap. Callers
     * that want a boolean should ask $user->withinLimit() directly.
     */
    public static function assert(User $user, string $limitKey, int $current): void
    {
        if ($user->withinLimit($limitKey, $current)) {
            return;
        }

        throw new PlanLimitExceeded(
            user: $user,
            limitKey: $limitKey,
            trigger: self::triggerFor($limitKey),
            current: $current,
        );
    }

    public static function triggerFor(string $limitKey): string
    {
        return self::TRIGGERS[$limitKey]
            ?? throw new \InvalidArgumentException("No trigger registered for plan limit [{$limitKey}].");
    }
}
