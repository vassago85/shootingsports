<?php

namespace App\Filament\Pages;

use App\Enums\ClaimStatus;
use App\Enums\EnquiryType;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Placements\PlacementResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Claim;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\Submission;
use App\Models\Venue;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class StaffDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Today';

    protected static ?string $title = 'Today';

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
        $pendingProviders = Provider::query()->where('status', ListingStatus::Pending)->count();
        $pendingClaims = Claim::query()->where('status', ClaimStatus::Pending)->count();
        $pendingSubmissions = Submission::query()->where('status', SubmissionStatus::Pending)->count();
        $openEnquiries = Enquiry::query()
            ->where('type', '!=', EnquiryType::MdSignup)
            ->awaitingStaff()
            ->count();
        $pendingMatches = Event::query()
            ->where('status', EventStatus::Draft)
            ->where('source', ListingSource::Submission)
            ->notFinished()
            ->count();

        return [
            'name' => auth()->user()?->name ?? 'Staff',
            'today' => now(config('app.timezone'))->format('l j F'),
            'tiles' => $this->headlineTiles(),
            'attention' => $this->attentionRows(
                $pendingMatches,
                $pendingOrgs,
                $openEnquiries,
                $pendingClaims,
                $pendingSubmissions,
                $pendingProviders,
            ),
            'upcoming' => $this->upcomingSevenDays(),
            'recent' => $this->recentActivity(),
            'addEventUrl' => EventResource::getUrl('create'),
        ];
    }

    /**
     * The four headline tiles (mockup s1). Each number links to a
     * filtered list that returns the same count; `go` is the subtitle
     * under the tile.
     *
     * @return list<array{label: string, value: int, href: string, go: string, tone: string}>
     */
    private function headlineTiles(): array
    {
        $inboxOpen = Inbox::countOpen();
        $eventsThisWeek = Event::query()
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Completed])
            ->count();

        $pendingClubs = Organisation::query()->where('status', ListingStatus::Pending)->count();
        $pendingRanges = Venue::query()->where('status', ListingStatus::Pending)->count();
        $pendingIndustry = Provider::query()->where('status', ListingStatus::Pending)->count();
        $listingsUnconfirmed = $pendingClubs + $pendingRanges + $pendingIndustry;

        $adsQuery = Placement::query()
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today());
        $adsLive = (clone $adsQuery)->count();
        $adViews = (int) (clone $adsQuery)->sum('impressions');
        $adClicks = (int) (clone $adsQuery)->sum('clicks');

        return [
            [
                'label' => 'Inbox items open',
                'value' => $inboxOpen,
                'href' => Inbox::getUrl(),
                'go' => 'Open inbox →',
                'tone' => $inboxOpen > 0 ? 'warning' : 'default',
            ],
            [
                'label' => 'Events this week',
                'value' => $eventsThisWeek,
                'href' => EventResource::getUrl('index', [
                    'tab' => 'upcoming',
                ]),
                'go' => 'Upcoming, next 7 days →',
                'tone' => 'default',
            ],
            [
                'label' => 'Listings unconfirmed',
                'value' => $listingsUnconfirmed,
                'href' => OrganisationResource::getUrl('index', [
                    'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
                ]),
                'go' => $pendingClubs.' clubs · '.$pendingRanges.' ranges · '.$pendingIndustry.' industry →',
                'tone' => $listingsUnconfirmed > 0 ? 'warning' : 'default',
            ],
            [
                'label' => 'Adverts live',
                'value' => $adsLive,
                'href' => PlacementResource::getUrl('index'),
                'go' => number_format($adViews).' views · '.number_format($adClicks).' clicks →',
                'tone' => 'default',
            ],
        ];
    }

    /**
     * "Fix these" rows — mockup s1 shape: title · count · severity badge.
     * Mockup-named rows first, then the rest of the live attention queue.
     *
     * @return list<array{severity: string, severity_label: string, title: string, count: string|int, href: string}>
     */
    private function attentionRows(int $pendingMatches, int $pendingOrgs, int $openEnquiries, int $pendingClaims, int $pendingSubmissions, int $pendingProviders): array
    {
        $rows = [];

        $push = function (string $severity, string $title, int|string $count, string $href) use (&$rows): void {
            $rows[] = [
                'severity' => $severity,
                'severity_label' => match ($severity) {
                    'critical' => 'Critical',
                    'attention' => 'Attention',
                    'suggestion' => 'Suggestion',
                    default => ucfirst($severity),
                },
                'title' => $title,
                'count' => $count,
                'href' => $href,
            ];
        };

        $missingGps = Venue::query()
            ->published()
            ->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))
            ->count();
        if ($missingGps > 0) {
            $push('critical', 'Ranges without GPS', $missingGps, VenueResource::getUrl('index', [
                'filters' => ['missing_gps' => ['isActive' => true]],
            ]));
        }

        $missingOrganiser = Event::query()->missingOrganiser()->count();
        if ($missingOrganiser > 0) {
            $push('critical', 'Events without a club', $missingOrganiser, EventResource::getUrl('index', [
                'filters' => ['missing_organiser' => ['isActive' => true]],
            ]));
        }

        if ($pendingMatches > 0) {
            $push('critical', 'Submitted matches', $pendingMatches, EventResource::getUrl('index', [
                'filters' => ['awaiting_approval' => ['isActive' => true]],
            ]));
        }

        $missingRegistration = Event::query()->missingRegistrationLink()->count();
        if ($missingRegistration > 0) {
            $push('attention', 'Events without a registration link', $missingRegistration, EventResource::getUrl('index', [
                'filters' => ['missing_registration_link' => ['isActive' => true]],
            ]));
        }

        $importedNoEnd = Event::query()
            ->where('source', ListingSource::Import)
            ->whereNull('ends_at')
            ->notFinished()
            ->excludingVerificationFixtures()
            ->count();
        if ($importedNoEnd > 0) {
            $push('attention', 'Imported events with no end date', $importedNoEnd, EventResource::getUrl('index', [
                'filters' => [
                    'source' => ['value' => ListingSource::Import->value],
                ],
            ]));
        }

        $missingSport = Event::query()->missingSport()->count();
        if ($missingSport > 0) {
            $push('critical', 'Events without a sport', $missingSport, EventResource::getUrl('index', [
                'filters' => ['missing_sport' => ['isActive' => true]],
            ]));
        }

        if ($pendingClaims > 0) {
            $push('attention', 'Club claims', $pendingClaims, ClaimResource::getUrl('index', [
                'filters' => ['status' => ['value' => ClaimStatus::Pending->value]],
            ]));
        }

        if ($pendingSubmissions > 0) {
            $push('attention', 'Register corrections', $pendingSubmissions, SubmissionResource::getUrl('index', [
                'filters' => ['status' => ['value' => SubmissionStatus::Pending->value]],
            ]));
        }

        if ($pendingOrgs > 0) {
            $push('attention', 'Clubs and series pending', $pendingOrgs, OrganisationResource::getUrl('index', [
                'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
            ]));
        }

        if ($pendingProviders > 0) {
            $push('attention', 'Industry listings pending', $pendingProviders, ProviderResource::getUrl('index', [
                'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
            ]));
        }

        if ($openEnquiries > 0) {
            $push('attention', 'Enquiries', $openEnquiries, Inbox::getUrl(['tab' => 'enquiries']));
        }

        $mdPending = $this->mdRequestsPending();
        if ($mdPending > 0) {
            $push('attention', 'Match director requests', $mdPending, Inbox::getUrl(['tab' => 'director_requests']));
        }

        $withoutDescription = Discipline::query()->withoutLongerDescription()->count();
        if ($withoutDescription > 0) {
            $push('suggestion', 'Sports without a long description', $withoutDescription, DisciplineResource::getUrl('index', [
                'filters' => ['without_longer_description' => ['isActive' => true]],
            ]));
        }

        return $rows;
    }

    /**
     * Compact list of the next seven days of events, soonest first.
     * Scoped to the public upcoming() scope so staff see what shooters
     * actually have on their calendar.
     *
     * @return list<array{title: string, when: string, host: ?string, href: string}>
     */
    private function upcomingSevenDays(): array
    {
        return Event::query()
            ->upcoming()
            ->with(['hostOrganisation', 'venue'])
            ->where('starts_at', '<=', now()->addDays(7))
            ->orderBy('starts_at')
            ->limit(6)
            ->get()
            ->map(fn (Event $event): array => [
                'title' => $event->title,
                'when' => $event->starts_at?->timezone(config('app.timezone'))->format('D j M'),
                'host' => $event->hostOrganisation?->name,
                'href' => EventResource::getUrl('edit', ['record' => $event]),
            ])
            ->all();
    }

    /**
     * Recent desk activity — mockup s1 badges (Pending review / Live).
     * Honest filler from recent Event / Club / Industry updates until
     * a real activity log lands.
     *
     * @return list<array{text: string, href: string, badge: string, badge_color: string}>
     */
    private function recentActivity(): array
    {
        $events = Event::query()
            ->with('hostOrganisation')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (Event $event): array {
                $pending = $event->publication === EventPublication::PendingReview
                    || $event->status === EventStatus::Draft;

                return [
                    'when' => $event->updated_at,
                    'text' => 'Director updated event: '.$event->title,
                    'href' => EventResource::getUrl('edit', ['record' => $event]),
                    'badge' => $pending ? 'Pending review' : 'Live',
                    'badge_color' => $pending ? 'info' : 'success',
                ];
            });

        $orgs = Organisation::query()
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Organisation $org): array => [
                'when' => $org->updated_at,
                'text' => 'Club listing updated: '.$org->name,
                'href' => OrganisationResource::getUrl('edit', ['record' => $org]),
                'badge' => $org->status === ListingStatus::Published ? 'Live' : 'Pending',
                'badge_color' => $org->status === ListingStatus::Published ? 'success' : 'warning',
            ]);

        $providers = Provider::query()
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Provider $provider): array => [
                'when' => $provider->updated_at,
                'text' => 'Industry listing updated: '.$provider->name,
                'href' => ProviderResource::getUrl('edit', ['record' => $provider]),
                'badge' => $provider->status === ListingStatus::Published ? 'Live' : 'Pending',
                'badge_color' => $provider->status === ListingStatus::Published ? 'success' : 'warning',
            ]);

        return $events->concat($orgs)->concat($providers)
            ->sortByDesc('when')
            ->take(8)
            ->map(fn (array $row): array => [
                'text' => $row['text'],
                'href' => $row['href'],
                'badge' => $row['badge'],
                'badge_color' => $row['badge_color'],
            ])
            ->values()
            ->all();
    }

    private function mdRequestsPending(): int
    {
        return Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->awaitingStaff()
            ->count();
    }

    /**
     * Phase 2 drops the waitlist panel from the dashboard view, but
     * Phase 3b will revive the counts once the Pro gates land. The
     * method stays alive so the aggregation query lives in one place
     * and the Phase 1 reflection test keeps passing.
     *
     * @return array<string, int>
     */
    private function waitlistThisMonth(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return Enquiry::query()
            ->where('type', EnquiryType::ProWaitlist)
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(fn (Enquiry $enquiry) => $enquiry->context['trigger'] ?? 'unknown')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();
    }
}
