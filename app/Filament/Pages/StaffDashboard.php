<?php

namespace App\Filament\Pages;

use App\Enums\ClaimStatus;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Placements\PlacementResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Claim;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\Submission;
use App\Models\User;
use App\Models\Venue;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class StaffDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'ShootingSports Admin';

    protected static ?string $slug = '';

    protected static ?int $navigationSort = -20;

    protected string $view = 'filament.pages.staff-dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
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
        $pendingMatches = Event::query()
            ->where('status', EventStatus::Draft)
            ->where('source', ListingSource::Submission)
            ->count();
        $pendingClaims = Claim::query()->where('status', ClaimStatus::Pending)->count();
        $pendingSubmissions = Submission::query()->where('status', SubmissionStatus::Pending)->count();
        $proSubscribers = $this->activeProSubscribers();

        return [
            'name' => auth()->user()?->name ?? 'Staff',
            'pendingOrgs' => $pendingOrgs,
            'newEnquiries' => $newEnquiries,
            'upcoming' => $upcoming,
            'waitlistThisMonth' => $this->waitlistThisMonth(),
            'mdRequestsPending' => $this->mdRequestsPending(),
            'mdApprovedThisMonth' => $this->mdApprovedThisMonth(),
            'proSubscribers' => $proSubscribers,
            'estimatedMrrCents' => $this->estimatedMrrCents($proSubscribers),
            'proSalesOpen' => (bool) config('plans.pro_enabled'),
            'pendingMatches' => $pendingMatches,
            'stats' => $this->registerStats($upcoming),
            'summary' => $this->attentionSummary($pendingMatches, $pendingOrgs, $newEnquiries, $pendingClaims, $pendingSubmissions),
            'attention' => $this->attentionRows($pendingMatches, $pendingOrgs, $newEnquiries, $pendingClaims, $pendingSubmissions),
            'activity' => $this->upcomingActivity(),
            'newest' => $this->newestRecords(),
            'submissions' => $this->recentSubmissions(),
            'platform' => $this->platformActivity(),
            'actions' => $this->quickActions(),
            'orgsUrl' => OrganisationResource::getUrl('index'),
            'eventsUrl' => EventResource::getUrl('index'),
            'pendingMatchesUrl' => $this->pendingMatchesUrl(),
            'enquiriesUrl' => EnquiryResource::getUrl('index'),
            'placementsUrl' => PlacementResource::getUrl('index'),
            'emailUrl' => ManageMailSettings::getUrl(),
            'emailLogUrl' => EmailLogResource::getUrl('index'),
            'pagePicturesUrl' => ManagePagePictures::getUrl(),
            'verificationUrl' => VerificationDashboard::getUrl(),
            'duplicatesUrl' => VenueDuplicates::getUrl(),
            'claimsUrl' => ClaimResource::getUrl('index'),
            'submissionsUrl' => SubmissionResource::getUrl('index'),
            'usersUrl' => UserResource::getUrl('index'),
            'mdPendingUrl' => UserResource::getUrl('index', ['tableFilters' => ['md_status' => ['value' => 'pending']]]),
        ];
    }

    /**
     * @return list<array{label: string, value: int, href: string}>
     */
    private function registerStats(int $upcoming): array
    {
        return [
            ['label' => 'Upcoming matches', 'value' => $upcoming, 'href' => EventResource::getUrl('index')],
            ['label' => 'Clubs', 'value' => Organisation::query()->clubs()->published()->count(), 'href' => OrganisationResource::getUrl('index')],
            ['label' => 'Ranges', 'value' => Venue::query()->published()->count(), 'href' => VenueResource::getUrl('index')],
            ['label' => 'Sports', 'value' => Discipline::query()->where('is_published', true)->count(), 'href' => DisciplineResource::getUrl('index')],
            ['label' => 'Industry', 'value' => Provider::query()->published()->count(), 'href' => ProviderResource::getUrl('index')],
            ['label' => 'Users', 'value' => User::query()->count(), 'href' => UserResource::getUrl('index')],
        ];
    }

    /**
     * @return list<array{severity: string, count: int, label: string}>
     */
    private function attentionSummary(int $pendingMatches, int $pendingOrgs, int $newEnquiries, int $pendingClaims, int $pendingSubmissions): array
    {
        $critical = $pendingMatches
            + $this->missingVenueCount()
            + $this->missingSportCount()
            + $this->missingOrganiserCount()
            + $this->missingGpsCount();

        $attention = $this->missingEntryCount()
            + $pendingOrgs
            + $newEnquiries
            + $this->mdRequestsPending()
            + $pendingClaims
            + $pendingSubmissions;

        $suggestion = Discipline::query()
            ->where('is_published', true)
            ->where(fn (Builder $query) => $query->whereNull('body')->orWhere('body', ''))
            ->count();

        return [
            ['severity' => 'critical', 'count' => $critical, 'label' => 'Critical'],
            ['severity' => 'attention', 'count' => $attention, 'label' => 'Needs attention'],
            ['severity' => 'suggestion', 'count' => $suggestion, 'label' => 'Suggestion'],
        ];
    }

    /**
     * @return list<array{severity: string, title: string, problem: string, href: string}>
     */
    private function attentionRows(int $pendingMatches, int $pendingOrgs, int $newEnquiries, int $pendingClaims, int $pendingSubmissions): array
    {
        $rows = [];

        if ($pendingMatches > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Submitted matches',
                'problem' => $pendingMatches.' waiting for approval',
                'href' => $this->pendingMatchesUrl(),
            ];
        }

        if ($pendingClaims > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Club claims',
                'problem' => $pendingClaims.' waiting for review',
                'href' => ClaimResource::getUrl('index'),
            ];
        }

        if ($pendingSubmissions > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Register corrections',
                'problem' => $pendingSubmissions.' waiting to be merged',
                'href' => SubmissionResource::getUrl('index'),
            ];
        }

        if ($pendingOrgs > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Clubs and series',
                'problem' => $pendingOrgs.' pending listing'.($pendingOrgs === 1 ? '' : 's'),
                'href' => OrganisationResource::getUrl('index'),
            ];
        }

        if ($newEnquiries > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Enquiries',
                'problem' => $newEnquiries.' new',
                'href' => EnquiryResource::getUrl('index'),
            ];
        }

        $mdPending = $this->mdRequestsPending();

        if ($mdPending > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Match director requests',
                'problem' => $mdPending.' awaiting review',
                'href' => UserResource::getUrl('index', ['tableFilters' => ['md_status' => ['value' => 'pending']]]),
            ];
        }

        $missingSport = $this->missingSportCount();

        if ($missingSport > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Matches',
                'problem' => $missingSport.' missing a sport',
                'href' => EventResource::getUrl('index'),
            ];
        }

        $missingOrganiser = $this->missingOrganiserCount();

        if ($missingOrganiser > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Matches',
                'problem' => $missingOrganiser.' missing an organiser',
                'href' => EventResource::getUrl('index'),
            ];
        }

        $missingVenue = $this->missingVenueCount();

        if ($missingVenue > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Matches',
                'problem' => $missingVenue.' missing a range',
                'href' => EventResource::getUrl('index'),
            ];
        }

        $missingEntry = $this->missingEntryCount();

        if ($missingEntry > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Matches',
                'problem' => $missingEntry.' missing a registration link',
                'href' => EventResource::getUrl('index'),
            ];
        }

        $missingGps = $this->missingGpsCount();

        if ($missingGps > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Ranges',
                'problem' => $missingGps.' missing GPS',
                'href' => VenueResource::getUrl('index'),
            ];
        }

        $thinSports = Discipline::query()
            ->where('is_published', true)
            ->where(fn (Builder $query) => $query->whereNull('body')->orWhere('body', ''))
            ->count();

        if ($thinSports > 0) {
            $rows[] = [
                'severity' => 'suggestion',
                'title' => 'Sports',
                'problem' => $thinSports.' without a longer description',
                'href' => DisciplineResource::getUrl('index'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{when: string, text: string}>
     */
    private function upcomingActivity(): array
    {
        $week = Event::query()->upcoming()->where('starts_at', '<=', now()->addDays(7))->count();
        $month = Event::query()->upcoming()->where('starts_at', '<=', now()->addDays(30))->count();
        $approved = $this->mdApprovedThisMonth();

        return [
            ['when' => 'This week', 'text' => $week.' upcoming '.($week === 1 ? 'match' : 'matches')],
            ['when' => '30 days', 'text' => $month.' upcoming '.($month === 1 ? 'match' : 'matches')],
            ['when' => 'This month', 'text' => $approved.' match director'.($approved === 1 ? '' : 's').' approved'],
        ];
    }

    /**
     * @return list<array{href: string, label: string}>
     */
    private function newestRecords(): array
    {
        return Event::query()
            ->where('slug', 'not like', 'verify-%')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Event $event): array => [
                'href' => EventResource::getUrl('edit', ['record' => $event]),
                'label' => $event->starts_at->timezone('Africa/Johannesburg')->format('j M Y').' — '.$event->title,
            ])
            ->all();
    }

    /**
     * @return list<array{href: string, label: string, meta: string}>
     */
    private function recentSubmissions(): array
    {
        $matches = Event::query()
            ->where('status', EventStatus::Draft)
            ->where('source', ListingSource::Submission)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Event $event): array => [
                'href' => EventResource::getUrl('edit', ['record' => $event]),
                'label' => $event->title,
                'meta' => 'Submitted match',
            ]);

        $claims = Claim::query()
            ->with('user')
            ->where('status', ClaimStatus::Pending)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Claim $claim): array => [
                'href' => ClaimResource::getUrl('edit', ['record' => $claim]),
                'label' => $claim->user?->name ?? 'Club claim',
                'meta' => 'Claim',
            ]);

        $corrections = Submission::query()
            ->where('status', SubmissionStatus::Pending)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Submission $submission): array => [
                'href' => SubmissionResource::getUrl('edit', ['record' => $submission]),
                'label' => $submission->submitter_name ?: 'Register correction',
                'meta' => $submission->type?->getLabel() ?? 'Submission',
            ]);

        return $matches->concat($claims)->concat($corrections)->values()->all();
    }

    /**
     * @return array{users: int, follows: int, impressions: int, clicks: int}
     */
    private function platformActivity(): array
    {
        return [
            'users' => User::query()->count(),
            'follows' => Follow::query()->count(),
            'impressions' => (int) Placement::query()->sum('impressions'),
            'clicks' => (int) Placement::query()->sum('clicks'),
        ];
    }

    /**
     * @return list<array{label: string, href: string, primary: bool}>
     */
    private function quickActions(): array
    {
        return [
            ['label' => 'Add match', 'href' => EventResource::getUrl('create'), 'primary' => true],
            ['label' => 'Add club', 'href' => OrganisationResource::getUrl('create'), 'primary' => false],
            ['label' => 'Add range', 'href' => VenueResource::getUrl('create'), 'primary' => false],
            ['label' => 'Add business', 'href' => ProviderResource::getUrl('create'), 'primary' => false],
            ['label' => 'Enquiries', 'href' => EnquiryResource::getUrl('index'), 'primary' => false],
            ['label' => 'Placements', 'href' => PlacementResource::getUrl('index'), 'primary' => false],
            ['label' => 'Verification', 'href' => VerificationDashboard::getUrl(), 'primary' => false],
            ['label' => 'Email & Turnstile', 'href' => ManageMailSettings::getUrl(), 'primary' => false],
            ['label' => 'Email log', 'href' => EmailLogResource::getUrl('index'), 'primary' => false],
            ['label' => 'Page pictures', 'href' => ManagePagePictures::getUrl(), 'primary' => false],
            ['label' => 'Venue duplicates', 'href' => VenueDuplicates::getUrl(), 'primary' => false],
            ['label' => 'Claims', 'href' => ClaimResource::getUrl('index'), 'primary' => false],
        ];
    }

    private function pendingMatchesUrl(): string
    {
        return EventResource::getUrl('index', [
            'tableFilters' => [
                'status' => ['value' => EventStatus::Draft->value],
                'source' => ['value' => ListingSource::Submission->value],
            ],
        ]);
    }

    private function missingVenueCount(): int
    {
        return Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->whereNull('venue_id')->count();
    }

    private function missingEntryCount(): int
    {
        return Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->where(function (Builder $query): void {
            $query->whereNull('entry_url')->orWhere('entry_url', '');
        })->count();
    }

    private function missingSportCount(): int
    {
        return Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->whereDoesntHave('disciplines')->count();
    }

    private function missingOrganiserCount(): int
    {
        return Event::query()
            ->where('status', '!=', EventStatus::Draft)
            ->where('slug', 'not like', 'verify-%')
            ->whereNull('host_organisation_id')
            ->count();
    }

    private function missingGpsCount(): int
    {
        return Venue::query()->published()->where(function (Builder $query): void {
            $query->whereNull('lat')->orWhereNull('lng');
        })->count();
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
     *
     * @param  array{annual: int, monthly: int, total: int}  $counts
     */
    private function estimatedMrrCents(array $counts): int
    {
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
