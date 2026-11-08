<?php

namespace App\Models\Concerns;

use App\Enums\ClubPlan;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Club subscription helpers. Plan checks for an organisation go through
 * this trait. An expired Club row behaves as Free with no nightly job.
 *
 * @property ClubPlan|string|null $plan
 * @property Carbon|null $plan_expires_at
 * @property Carbon|null $plan_cancelled_at
 */
trait HasClubPlan
{
    public function clubPlan(): ClubPlan
    {
        $value = $this->plan ?? ClubPlan::Free;

        if ($value instanceof ClubPlan) {
            return $value;
        }

        return ClubPlan::tryFrom((string) $value) ?? ClubPlan::Free;
    }

    /**
     * True when the club plan is Club and the window is open-ended or
     * still in the future.
     */
    public function isPaid(): bool
    {
        if ($this->clubPlan() !== ClubPlan::Club) {
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

    public function effectiveClubPlan(): ClubPlan
    {
        return $this->isPaid() ? ClubPlan::Club : ClubPlan::Free;
    }

    /**
     * Boolean feature from config/club-plans.php. Unknown keys throw
     * so a typo cannot silently unlock a tool.
     */
    public function allowsClubFeature(string $key): bool
    {
        $plan = $this->effectiveClubPlan()->value;
        $features = config("club-plans.{$plan}", []);

        if (! is_array($features) || ! array_key_exists($key, $features)) {
            throw new \InvalidArgumentException(
                "Unknown club plan feature [{$key}] for plan [{$plan}]."
            );
        }

        return (bool) $features[$key];
    }

    /**
     * Clubs whose subscription is currently paid. Expired rows are excluded.
     */
    #[Scope]
    protected function paid(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.plan", ClubPlan::Club->value)
            ->where(function (Builder $inner) use ($table): void {
                $inner->whereNull("{$table}.plan_expires_at")
                    ->orWhere("{$table}.plan_expires_at", '>', now());
            });
    }
}
