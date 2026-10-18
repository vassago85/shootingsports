<?php

namespace App\Filament\Desk\Pages;

use App\Enums\ClubMemberStatus;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\VerificationState;
use App\Filament\Desk\Resources\Articles\ArticleResource;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Filament\Desk\Resources\Events\EventResource;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use App\Models\ClubMember;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Phase C — single /desk home that switches between director,
 * industry, media-partner and no-access landings based on the
 * signed-in user's capabilities. Staff with no listings of their
 * own still land on the director empty state (own-only, no bypass).
 */
class DeskDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Desk';

    protected static ?string $slug = '';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.desk.pages.desk-dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        return match ($this->mode()) {
            'director' => 'Match director desk',
            'industry' => 'Industry desk',
            'media' => 'Media desk',
            default => 'Desk',
        };
    }

    /**
     * Mockup puts the eyebrow + H1 in the page body; hide Filament's
     * duplicate page heading chrome.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $mode = $this->mode();

        return match ($mode) {
            'director' => $this->directorData($user),
            'industry' => $this->industryData($user),
            'media' => $this->mediaData($user),
            default => $this->noAccessData(),
        };
    }

    protected function mode(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->deskHomeMode() : 'none';
    }

    /**
     * @return array<string, mixed>
     */
    protected function directorData(?User $user): array
    {
        $memberships = $user === null
            ? collect()
            : OrganisationUser::query()
                ->where('user_id', $user->id)
                ->where(function ($q): void {
                    // Pre-Phase-B rows may still have a null status until
                    // the migration default has been applied everywhere.
                    $q->where('status', MembershipStatus::Approved->value)
                        ->orWhereNull('status');
                })
                ->with('organisation')
                ->get();

        $orgIds = $memberships->pluck('organisation_id');

        // Legacy MD flag (pre-Phase B) still counts: they may have
        // created events under hosts without a pivot row yet.
        if ($user?->is_match_director && $orgIds->isEmpty()) {
            $orgIds = $user->organisations()->pluck('organisations.id');
        }

        $clubRows = $memberships->map(function (OrganisationUser $row): ?array {
            $org = $row->organisation;
            if ($org === null) {
                return null;
            }

            return [
                'name' => $org->name,
                'role' => $row->role?->getLabel() ?? 'Member',
                'verified' => $org->verification_state === VerificationState::Verified,
                'status_label' => $org->verification_state === VerificationState::Verified ? 'Verified' : 'Unconfirmed',
                'href' => OrganisationResource::getUrl('edit', ['record' => $org]),
            ];
        })->filter()->values();

        if ($clubRows->isEmpty() && $orgIds->isNotEmpty() && $user !== null) {
            $clubRows = Organisation::query()
                ->whereIn('id', $orgIds)
                ->get()
                ->map(fn (Organisation $org): array => [
                    'name' => $org->name,
                    'role' => 'Match director',
                    'verified' => $org->verification_state === VerificationState::Verified,
                    'status_label' => $org->verification_state === VerificationState::Verified ? 'Verified' : 'Unconfirmed',
                    'href' => OrganisationResource::getUrl('edit', ['record' => $org]),
                ]);
        }

        $clubRows = $clubRows->all();

        $baseQuery = Event::query()->whereIn('host_organisation_id', $orgIds);

        $upcoming = $orgIds->isEmpty()
            ? new Collection
            : (clone $baseQuery)
                ->where('starts_at', '>=', now()->startOfDay())
                ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Completed])
                ->with('hostOrganisation')
                ->orderBy('starts_at')
                ->limit(8)
                ->get();

        $pendingReview = $orgIds->isEmpty()
            ? 0
            : (clone $baseQuery)->where('publication', EventPublication::PendingReview)->count();

        return [
            'mode' => 'director',
            'name' => $user?->name ?? 'Director',
            'clubCount' => count($clubRows),
            'upcomingCount' => $upcoming->count(),
            'pendingReviewCount' => $pendingReview,
            'upcomingEvents' => $upcoming->map(function (Event $event): array {
                $completeness = $event->listingCompleteness();

                return [
                    'title' => $event->title,
                    'host' => $event->hostOrganisation?->name,
                    'date' => $event->starts_at->timezone('Africa/Johannesburg')->format('D j M'),
                    'status' => $event->status?->getLabel() ?? '',
                    'status_color' => $event->status?->getColor() ?? 'gray',
                    // Mockup labels: PUBLISHED / IN REVIEW / DRAFT
                    'publication' => match ($event->publication) {
                        EventPublication::Published => 'Published',
                        EventPublication::PendingReview => 'In review',
                        EventPublication::Rejected => 'Rejected',
                        default => 'Draft',
                    },
                    'publication_color' => match ($event->publication) {
                        EventPublication::Published => 'success',
                        EventPublication::PendingReview => 'info',
                        EventPublication::Rejected => 'danger',
                        default => 'gray',
                    },
                    'percent' => $completeness['percent'],
                    'hints' => $completeness['hints'],
                    'href' => EventResource::getUrl('edit', ['record' => $event]),
                ];
            })->all(),
            'clubs' => $clubRows,
            'createEventUrl' => EventResource::getUrl('create'),
            'eventsUrl' => EventResource::getUrl('index'),
            'listingsUrl' => OrganisationResource::getUrl('index'),
            'claimUrl' => ClaimListing::getUrl(),
            'createListingUrl' => OrganisationResource::getUrl('create'),
            'membership' => $this->membershipSnapshot($user),
        ];
    }

    /**
     * Totals + per-club breakdown for the membership-manager card on
     * the director dashboard. Hidden entirely when the user does not
     * hold an Admin/Secretary seat on any club.
     *
     * @return array<string, mixed>
     */
    protected function membershipSnapshot(?User $user): array
    {
        if ($user === null || ! $user->holdsAnyMemberManagingRole()) {
            return ['visible' => false];
        }

        $clubIds = $user->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageMembers(),
            ))
            ->pluck('organisation_id');

        if ($clubIds->isEmpty()) {
            return ['visible' => false];
        }

        $today = CarbonImmutable::today();
        $windowEnd = $today->addDays(30);

        $totals = [
            'active' => ClubMember::query()
                ->whereIn('organisation_id', $clubIds)
                ->active()
                ->count(),
            'applied' => ClubMember::query()
                ->whereIn('organisation_id', $clubIds)
                ->where('status', ClubMemberStatus::Applied)
                ->count(),
            'lapsed' => ClubMember::query()
                ->whereIn('organisation_id', $clubIds)
                ->where(function ($q): void {
                    $q->where('status', ClubMemberStatus::Lapsed)
                        ->orWhere(function ($qq): void {
                            $qq->where('status', ClubMemberStatus::Active)
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<', CarbonImmutable::today());
                        });
                })
                ->count(),
            'expiring' => ClubMember::query()
                ->whereIn('organisation_id', $clubIds)
                ->expiringWithin(30)
                ->count(),
        ];

        return [
            'visible' => true,
            'totals' => $totals,
            'membersUrl' => ClubMemberResource::getUrl('index'),
            'expiringUrl' => ClubMemberResource::getUrl('index', ['tableFilters' => ['expiring_soon' => ['isActive' => true]]]),
            'appliedUrl' => ClubMemberResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ClubMemberStatus::Applied->value]]]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function industryData(?User $user): array
    {
        $provider = $user?->providers()->orderByDesc('updated_at')->first()
            ?? ($user !== null
                ? Provider::query()
                    ->whereHas(
                        'memberships',
                        fn ($q) => $q->where('user_id', $user->id)
                            ->where('status', MembershipStatus::Approved->value),
                    )
                    ->orderByDesc('updated_at')
                    ->first()
                : null);

        $placementStats = $provider === null
            ? ['impressions' => 0, 'clicks' => 0]
            : Placement::query()
                ->where('provider_id', $provider->id)
                ->selectRaw('COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
                ->first();

        $enquiriesThisMonth = $provider === null
            ? 0
            : Enquiry::query()
                ->where('about_type', Provider::class)
                ->where('about_id', $provider->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

        $hasLogo = filled($provider?->logo_path);
        $hasDescription = filled($provider?->description) || filled($provider?->tagline);
        $hasContact = filled($provider?->email) || filled($provider?->phone);
        $needsConfirm = $provider === null
            || $provider->last_verified_at === null
            || $provider->last_verified_at->lt(now()->subMonths(6));

        $editUrl = $provider !== null
            ? route('suppliers.onboard.edit', ['provider' => $provider->slug])
            : route('suppliers.onboard');

        return [
            'mode' => 'industry',
            'businessName' => $provider?->name ?? 'Your business',
            'impressions' => (int) ($placementStats->impressions ?? 0),
            'clicks' => (int) ($placementStats->clicks ?? 0),
            'enquiriesThisMonth' => $enquiriesThisMonth,
            'health' => [
                ['label' => 'Logo and photos', 'ok' => $hasLogo, 'status' => $hasLogo ? 'Done' : 'Missing'],
                ['label' => 'Description', 'ok' => $hasDescription, 'status' => $hasDescription ? 'Done' : 'Missing'],
                ['label' => 'Contact details', 'ok' => $hasContact, 'status' => $hasContact ? 'Done' : 'Missing'],
                [
                    'label' => 'Confirm listing details (every 6 months)',
                    'ok' => ! $needsConfirm,
                    'status' => $needsConfirm ? 'Due' : 'Done',
                    'action' => $needsConfirm ? $editUrl : null,
                ],
            ],
            'editUrl' => $editUrl,
            'advertiseUrl' => route('advertise'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mediaData(?User $user): array
    {
        return [
            'mode' => 'media',
            'name' => $user?->name ?? 'Partner',
            'articlesUrl' => ArticleResource::getUrl('index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function noAccessData(): array
    {
        return [
            'mode' => 'none',
            'claimUrl' => ClaimListing::getUrl(),
            'listBusinessUrl' => route('suppliers.start'),
            'calendarUrl' => route('my-calendar'),
        ];
    }
}
