<?php

namespace App\Models\Concerns;

use App\Enums\Plan;
use Illuminate\Support\Carbon;

/**
 * Freemium plan helpers for User. All plan checks in the app MUST go
 * through this trait — never compare $user->plan strings directly in
 * Livewire, Blade or controllers. Adding a new plan is then a single
 * change: add the enum case and its config/plans.php row.
 *
 * @property string|null $plan
 * @property Carbon|null $plan_expires_at
 */
trait HasPlan
{
    public function plan(): Plan
    {
        $value = $this->plan ?? Plan::Free;

        if ($value instanceof Plan) {
            return $value;
        }

        return Plan::tryFrom((string) $value) ?? Plan::Free;
    }

    /**
     * True only when the user's plan is Pro AND the entitlement window
     * is either open-ended (null) or in the future. An expired Pro row
     * degrades to Free without any nightly job.
     */
    public function isPro(): bool
    {
        if ($this->plan() !== Plan::Pro) {
            return false;
        }

        $expiresAt = $this->plan_expires_at;

        if ($expiresAt === null) {
            return true;
        }

        return $expiresAt instanceof Carbon
            ? $expiresAt->isFuture()
            : Carbon::parse($expiresAt)->isFuture();
    }

    /**
     * Effective plan after applying the expiry rule. Reads elsewhere
     * should prefer this over $this->plan() so an expired Pro looks
     * like Free to every consumer.
     */
    public function effectivePlan(): Plan
    {
        return $this->isPro() ? Plan::Pro : Plan::Free;
    }

    /**
     * Config value for a given limit key. Returns null when unlimited.
     * Returns null with a warning if the key is unknown — callers that
     * treat null as "unlimited" would then silently allow unbounded
     * writes, so this method throws instead to catch typos in review.
     */
    public function limit(string $key): ?int
    {
        $plan = $this->effectivePlan()->value;
        $limits = config("plans.{$plan}", []);

        if (! array_key_exists($key, $limits)) {
            throw new \InvalidArgumentException(
                "Unknown plan limit key [{$key}] for plan [{$plan}]."
            );
        }

        $value = $limits[$key];

        // Boolean feature flags (export, ...) coerce to null via allows().
        if (is_bool($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Boolean feature flag (e.g. export, household). Returns true when
     * the plan has the feature enabled, false otherwise. Integer caps
     * ("do you have follows at all?") always allow — use withinLimit()
     * to check the volume.
     */
    public function allows(string $key): bool
    {
        $plan = $this->effectivePlan()->value;
        $value = config("plans.{$plan}.{$key}");

        if (is_bool($value)) {
            return $value;
        }

        // Numeric cap of 0 = feature disabled; anything else (including
        // null = unlimited) counts as allowed.
        if (is_int($value)) {
            return $value > 0;
        }

        return $value === null; // null in an int slot means unlimited.
    }

    /**
     * True when the current usage count is still below the cap. null
     * limit (unlimited) always returns true.
     */
    public function withinLimit(string $key, int $current): bool
    {
        $limit = $this->limit($key);

        if ($limit === null) {
            return true;
        }

        return $current < $limit;
    }

    /**
     * How many more of $key the user may create. null = unlimited.
     * Never negative; a user already over their cap gets 0.
     */
    public function remaining(string $key, int $current): ?int
    {
        $limit = $this->limit($key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $current);
    }
}
