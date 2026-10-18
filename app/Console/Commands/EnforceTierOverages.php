<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly sweep: recomputes each subscribed club's active member count,
 * stamps a grace window the first time the count exceeds the tier cap,
 * clears it when the club is back within the cap, and auto-upgrades the
 * subscription once the grace window has expired. Trial subscriptions are
 * recounted and keep their grace date (to warn the admin) but are never
 * auto-upgraded; cancelled subscriptions have the grace date cleared. A club
 * already on the top tier keeps its grace date and is flagged for review.
 */
#[AsCommand(name: 'platform:enforce-tier-overages')]
class EnforceTierOverages extends Command
{
    protected $signature = 'platform:enforce-tier-overages {--dry-run : Report changes without writing}';

    protected $description = 'Recompute active member counts, stamp tier grace windows and auto-upgrade expired overages.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orgCount = 0;
        $graceSet = 0;
        $upgradeCount = 0;
        $skippedUpgrades = 0;

        Organisation::query()
            ->whereHas('platformSubscription')
            ->with('platformSubscription')
            ->orderBy('id')
            ->each(function (Organisation $org) use ($dryRun, &$orgCount, &$graceSet, &$upgradeCount, &$skippedUpgrades): void {
                $orgCount++;

                DB::transaction(function () use ($org, $dryRun, &$graceSet, &$upgradeCount, &$skippedUpgrades): void {
                    $subscription = $org->platformSubscription;
                    $activeCount = $org->clubMembers()
                        ->where('status', ClubMemberStatus::Active->value)
                        ->count();
                    $cap = $subscription->tier->memberCap();
                    $isCancelled = $subscription->status === PlatformSubscriptionStatus::Cancelled;
                    $upgradeAllowed = ! $isCancelled && $subscription->status !== PlatformSubscriptionStatus::Trial;

                    $org->active_member_count = $activeCount;

                    if ($isCancelled || $activeCount <= $cap) {
                        $org->tier_grace_ends_at = null;
                    } elseif ($org->tier_grace_ends_at === null) {
                        $org->tier_grace_ends_at = today()->addDays((int) config('platform.grace_window_days', 30));
                        $graceSet++;
                        $this->line("[{$org->name}] {$activeCount} members exceeds {$subscription->tier->value} cap of {$cap}: grace window until {$org->tier_grace_ends_at->toDateString()}.");
                    } elseif ($org->tier_grace_ends_at->isPast()) {
                        if (! $upgradeAllowed) {
                            $skippedUpgrades++;
                            $this->line("[{$org->name}] grace expired but subscription is {$subscription->status->value}: auto-upgrade skipped.");
                        } else {
                            $newTier = SubscriptionTier::forMemberCount($activeCount);

                            if ($newTier === $subscription->tier) {
                                $this->warn("{$org->name}: on Large tier at {$activeCount} members — cap exceeded, no higher tier available. Review required.");
                            } else {
                                $this->line("[{$org->name}] auto-upgrade {$subscription->tier->value} -> {$newTier->value}.");
                                $subscription->tier = $newTier;
                                $upgradeCount++;

                                if (! $dryRun) {
                                    $subscription->save();
                                }

                                $org->tier_grace_ends_at = null;
                            }
                        }
                    }

                    if (! $dryRun) {
                        $org->save();
                    }
                });
            });

        $summary = "Processed {$orgCount} organisations, {$graceSet} new grace windows, {$upgradeCount} auto-upgrades";

        if ($skippedUpgrades > 0) {
            $summary .= " ({$skippedUpgrades} auto-upgrades skipped for trial/cancelled subscriptions)";
        }

        $this->info($dryRun ? "[dry-run] {$summary}" : $summary);

        return self::SUCCESS;
    }
}
