<?php

namespace App\Filament\Pages;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\ClubPayable;
use App\Models\MembershipInvoice;
use App\Models\PlatformSubscription;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Read-only, staff-only revenue dashboard for the platform billing
 * model: subscription MRR/ARR, platform fee revenue, club payables.
 */
class PlatformRevenue extends Page
{
    protected string $view = 'filament.pages.platform-revenue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Revenue';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'platform-revenue';

    protected static ?string $title = 'Platform revenue';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    /**
     * @return array{
     *     mrr_cents: int,
     *     arr_cents: int,
     *     trial_to_paid_pct: int,
     *     active_subscriptions_by_tier: array<string, int>,
     *     fee_revenue_30d_cents: int,
     *     fee_revenue_vat_30d_cents: int,
     *     outstanding_payables_cents: int,
     *     failed_charges_30d: int,
     * }
     */
    public function tiles(): array
    {
        $mrrCents = $this->mrrCents();

        return [
            'mrr_cents' => $mrrCents,
            'arr_cents' => $mrrCents * 12,
            'trial_to_paid_pct' => $this->trialToPaidPercent(30),
            'active_subscriptions_by_tier' => $this->activeSubscriptionsByTier(),
            'fee_revenue_30d_cents' => $this->feeRevenueCents('platform_fee_cents', 30),
            'fee_revenue_vat_30d_cents' => $this->feeRevenueCents('platform_fee_vat_cents', 30),
            'outstanding_payables_cents' => $this->outstandingPayablesCents(),
            'failed_charges_30d' => $this->failedChargesCount(30),
        ];
    }

    /**
     * Monthly-equivalent price of every active subscription (annual / 12).
     */
    private function mrrCents(): int
    {
        return PlatformSubscription::query()
            ->where('status', PlatformSubscriptionStatus::Active)
            ->get(['tier', 'interval'])
            ->sum(fn (PlatformSubscription $subscription): int => match ($subscription->interval) {
                BillingInterval::Annual => (int) round($subscription->tier->priceCents(BillingInterval::Annual) / 12),
                default => $subscription->tier->priceCents(BillingInterval::Monthly),
            });
    }

    /**
     * Of subscriptions created in the window that have a settled-or-
     * running outcome (Active / Trial / TrialExpired), the share now Active.
     */
    private function trialToPaidPercent(int $days): int
    {
        $cohort = PlatformSubscription::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', [
                PlatformSubscriptionStatus::Active,
                PlatformSubscriptionStatus::Trial,
                PlatformSubscriptionStatus::TrialExpired,
            ])
            ->toBase()
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as converted', [
                PlatformSubscriptionStatus::Active->value,
            ])
            ->first();

        $total = (int) ($cohort->total ?? 0);

        if ($total === 0) {
            return 0;
        }

        return (int) round(((int) $cohort->converted / $total) * 100);
    }

    /**
     * @return array<string, int> tier label => active subscription count
     */
    private function activeSubscriptionsByTier(): array
    {
        $counts = PlatformSubscription::query()
            ->where('status', PlatformSubscriptionStatus::Active)
            ->toBase()
            ->selectRaw('tier, count(*) as c')
            ->groupBy('tier')
            ->pluck('c', 'tier');

        $byTier = [];

        foreach (SubscriptionTier::cases() as $tier) {
            $byTier[$tier->label()] = (int) ($counts[$tier->value] ?? 0);
        }

        return $byTier;
    }

    private function feeRevenueCents(string $column, int $days): int
    {
        return (int) MembershipInvoice::query()
            ->where('paid_at', '>=', now()->subDays($days))
            ->sum($column);
    }

    private function outstandingPayablesCents(): int
    {
        return (int) ClubPayable::query()->pending()->sum('net_due_cents');
    }

    /**
     * No failed-charge marker exists on platform_payments (only successful
     * payments are stored), so this approximates with subscriptions that
     * entered PastDue within the window.
     */
    private function failedChargesCount(int $days): int
    {
        return PlatformSubscription::query()
            ->where('status', PlatformSubscriptionStatus::PastDue)
            ->where('updated_at', '>=', now()->subDays($days))
            ->count();
    }
}
