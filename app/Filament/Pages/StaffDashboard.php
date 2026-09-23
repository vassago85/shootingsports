<?php

namespace App\Filament\Pages;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Placements\PlacementResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class StaffDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Staff desk';

    protected static ?string $slug = '';

    protected static ?int $navigationSort = -20;

    protected string $view = 'filament.pages.staff-dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $pendingOrgs = Organisation::query()->where('status', ListingStatus::Pending)->count();
        $newEnquiries = Enquiry::query()->where('status', EnquiryStatus::New)->count();
        $upcoming = Event::query()->upcoming()->count();

        return [
            'name' => auth()->user()?->name ?? 'Staff',
            'pendingOrgs' => $pendingOrgs,
            'newEnquiries' => $newEnquiries,
            'upcoming' => $upcoming,
            'waitlistThisMonth' => $this->waitlistThisMonth(),
            'mdRequestsPending' => $this->mdRequestsPending(),
            'mdApprovedThisMonth' => $this->mdApprovedThisMonth(),
            'proSubscribers' => $this->activeProSubscribers(),
            'estimatedMrrCents' => $this->estimatedMrrCents(),
            'orgsUrl' => OrganisationResource::getUrl('index'),
            'createOrgUrl' => OrganisationResource::getUrl('create'),
            'eventsUrl' => EventResource::getUrl('index'),
            'createEventUrl' => EventResource::getUrl('create'),
            'pendingMatches' => Event::query()
                ->where('status', EventStatus::Draft)
                ->where('source', ListingSource::Submission)
                ->count(),
            'pendingMatchesUrl' => EventResource::getUrl('index', [
                'tableFilters' => [
                    'status' => ['value' => EventStatus::Draft->value],
                    'source' => ['value' => ListingSource::Submission->value],
                ],
            ]),
            'enquiriesUrl' => EnquiryResource::getUrl('index'),
            'placementsUrl' => PlacementResource::getUrl('index'),
            'emailUrl' => ManageMailSettings::getUrl(),
            'verificationUrl' => VerificationDashboard::getUrl(),
            'usersUrl' => UserResource::getUrl('index'),
            'mdPendingUrl' => UserResource::getUrl('index', ['tableFilters' => ['md_status' => ['value' => 'pending']]]),
        ];
    }

    /**
     * Count of users with a Paystack subscription code AND a non-past
     * plan_expires_at AND no cancelled_at. Comps (Pro without a
     * subscription code) are deliberately excluded from the subscriber
     * count and MRR — they aren't recurring revenue.
     *
     * @return array{annual:int, monthly:int, total:int}
     */
    private function activeProSubscribers(): array
    {
        $base = User::query()
            ->whereNotNull('paystack_subscription_code')
            ->whereNull('plan_cancelled_at')
            ->where('plan_expires_at', '>', now());

        $annual = (clone $base)->where('plan_billing_cycle', 'annual')->count();
        $monthly = (clone $base)->where('plan_billing_cycle', 'monthly')->count();

        return [
            'annual' => $annual,
            'monthly' => $monthly,
            'total' => $annual + $monthly,
        ];
    }

    /**
     * Rough monthly recurring revenue in cents. Annuals are amortised
     * over 12 months at the currently configured annual price; monthlies
     * are counted at their own price. Grandfathered subscribers on a
     * previous price will make this off by a few rand — good enough
     * as a "how are we doing" number, not for accounting.
     */
    private function estimatedMrrCents(): int
    {
        $counts = $this->activeProSubscribers();

        $annualCents = (int) config('plans.pricing.annual.amount_cents');
        $monthlyCents = (int) config('plans.pricing.monthly.amount_cents');

        return ($counts['annual'] * intdiv($annualCents, 12)) + ($counts['monthly'] * $monthlyCents);
    }

    /**
     * How many MD requests are sitting in the queue right now. This
     * is the number the dashboard needs to prompt action on — if the
     * count is non-zero, the widget shows a "review now" CTA.
     */
    private function mdRequestsPending(): int
    {
        return User::query()
            ->whereNotNull('md_requested_at')
            ->where('is_match_director', false)
            ->whereNull('md_rejected_at')
            ->count();
    }

    /**
     * MD requests successfully approved this calendar month — a
     * "throughput" metric to see whether the review lane is being
     * worked. Deliberately scoped to md_approved_at (not created_at)
     * so grandfathered pre-review-flow MDs do not skew it upward.
     */
    private function mdApprovedThisMonth(): int
    {
        return User::query()
            ->where('is_match_director', true)
            ->where('is_staff', false)
            ->whereBetween('md_approved_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /**
     * Pro waitlist signups this calendar month, bucketed by trigger.
     * Postgres-friendly: reads context->>'trigger' via Eloquent's JSON
     * accessor rather than a driver-specific SQL fragment.
     *
     * @return array<string, int>
     */
    private function waitlistThisMonth(): array
    {
        $rows = Enquiry::query()
            ->where('type', EnquiryType::ProWaitlist)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['context']);

        $counts = [];
        foreach ($rows as $row) {
            $trigger = data_get($row->context, 'trigger') ?: 'unknown';
            $counts[$trigger] = ($counts[$trigger] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
