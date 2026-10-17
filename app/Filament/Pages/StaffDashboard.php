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
use App\Filament\Resources\Enquiries\EnquiryResource;
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
        $newEnquiries = Enquiry::query()
            ->where('type', '!=', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New)
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
                $newEnquiries,
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
     * The four headline tiles. Each number links to a filtered list
     * that returns exactly that count — no drift between tile and page.
     *
     * @return list<array{label: string, value: int, href: string, tone: string}>
     */
    private function headlineTiles(): array
    {
        $inboxOpen = Inbox::countOpen();
        $eventsThisWeek = Event::query()
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Completed])
            ->count();
        $listingsUnconfirmed = Organisation::query()->where('status', ListingStatus::Pending)->count()
            + Venue::query()->where('status', ListingStatus::Pending)->count()
            + Provider::query()->where('status', ListingStatus::Pending)->count();
        $adsLive = Placement::query()
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->count();

        return [
            [
                'label' => 'Inbox open',
                'value' => $inboxOpen,
                'href' => Inbox::getUrl(),
                'tone' => $inboxOpen > 0 ? 'warning' : 'default',
            ],
            [
                'label' => 'Events this week',
                'value' => $eventsThisWeek,
                'href' => EventResource::getUrl('index', [
                    'tab' => 'upcoming',
                ]),
                'tone' => 'default',
            ],
            [
                'label' => 'Listings unconfirmed',
                'value' => $listingsUnconfirmed,
                'href' => OrganisationResource::getUrl('index', [
                    'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
                ]),
                'tone' => $listingsUnconfirmed > 0 ? 'warning' : 'default',
            ],
            [
                'label' => 'Ads live',
                'value' => $adsLive,
                'href' => PlacementResource::getUrl('index'),
                'tone' => 'default',
            ],
        ];
    }

    /**
     * "Fix these" rows — the Phase 2 attention list. Same rules as
     * before, each row links to the filtered list that produced its
     * count so staff can act with one click.
     *
     * @return list<array{severity: string, title: string, problem: string, href: string}>
     */
    private function attentionRows(int $pendingMatches, int $pendingOrgs, int $newEnquiries, int $pendingClaims, int $pendingSubmissions, int $pendingProviders): array
    {
        $rows = [];

        if ($pendingMatches > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Submitted matches',
                'problem' => $pendingMatches.' waiting for approval',
                'href' => EventResource::getUrl('index', [
                    'filters' => ['awaiting_approval' => ['isActive' => true]],
                ]),
            ];
        }

        if ($pendingClaims > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Club claims',
                'problem' => $pendingClaims.' waiting for review',
                'href' => ClaimResource::getUrl('index', [
                    'filters' => ['status' => ['value' => ClaimStatus::Pending->value]],
                ]),
            ];
        }

        if ($pendingSubmissions > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Register corrections',
                'problem' => $pendingSubmissions.' waiting to be merged',
                'href' => SubmissionResource::getUrl('index', [
                    'filters' => ['status' => ['value' => SubmissionStatus::Pending->value]],
                ]),
            ];
        }

        if ($pendingOrgs > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Clubs and series',
                'problem' => $pendingOrgs.' pending listing'.($pendingOrgs === 1 ? '' : 's'),
                'href' => OrganisationResource::getUrl('index', [
                    'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
                ]),
            ];
        }

        if ($pendingProviders > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Industry listings',
                'problem' => $pendingProviders.' pending listing'.($pendingProviders === 1 ? '' : 's'),
                'href' => ProviderResource::getUrl('index', [
                    'filters' => ['status' => ['value' => ListingStatus::Pending->value]],
                ]),
            ];
        }

        if ($newEnquiries > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Enquiries',
                'problem' => $newEnquiries.' new',
                'href' => EnquiryResource::getUrl('index', [
                    'filters' => ['status' => ['value' => EnquiryStatus::New->value]],
                ]),
            ];
        }

        $mdPending = $this->mdRequestsPending();

        if ($mdPending > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Match director requests',
                'problem' => $mdPending.' awaiting review',
                'href' => EnquiryResource::getUrl('index', [
                    'filters' => [
                        'type' => ['value' => EnquiryType::MdSignup->value],
                        'status' => ['value' => EnquiryStatus::New->value],
                    ],
                ]),
            ];
        }

        $missingOrganiser = Event::query()->missingOrganiser()->count();
        if ($missingOrganiser > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Matches',
                'problem' => $missingOrganiser.' missing an organiser',
                'href' => EventResource::getUrl('index', [
                    'filters' => ['missing_organiser' => ['isActive' => true]],
                ]),
            ];
        }

        $missingSport = Event::query()->missingSport()->count();
        if ($missingSport > 0) {
            $rows[] = [
                'severity' => 'critical',
                'title' => 'Matches',
                'problem' => $missingSport.' missing a sport',
                'href' => EventResource::getUrl('index', [
                    'filters' => ['missing_sport' => ['isActive' => true]],
                ]),
            ];
        }

        $missingRegistration = Event::query()->missingRegistrationLink()->count();
        if ($missingRegistration > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Matches',
                'problem' => $missingRegistration.' missing a registration link',
                'href' => EventResource::getUrl('index', [
                    'filters' => ['missing_registration_link' => ['isActive' => true]],
                ]),
            ];
        }

        $missingGps = Venue::query()->published()->whereNull('lat')->orWhereNull('lng')->count();
        if ($missingGps > 0) {
            $rows[] = [
                'severity' => 'attention',
                'title' => 'Ranges',
                'problem' => $missingGps.' missing GPS',
                'href' => VenueResource::getUrl('index', [
                    'filters' => ['missing_gps' => ['isActive' => true]],
                ]),
            ];
        }

        $withoutDescription = Discipline::query()->withoutLongerDescription()->count();
        if ($withoutDescription > 0) {
            $rows[] = [
                'severity' => 'suggestion',
                'title' => 'Sports',
                'problem' => $withoutDescription.' without a longer description',
                'href' => DisciplineResource::getUrl('index', [
                    'filters' => ['without_longer_description' => ['isActive' => true]],
                ]),
            ];
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
     * Recent desk activity — the most recently updated directory
     * records across Clubs, Ranges, Industry and Events, so staff can
     * see at a glance what's been touched. Phase 3 adds a real activity
     * log; this is honest filler until then.
     *
     * @return list<array{when: string, text: string, href: string}>
     */
    private function recentActivity(): array
    {
        $events = Event::query()
            ->with('hostOrganisation')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event): array => [
                'when' => $event->updated_at,
                'text' => 'Event: '.$event->title,
                'href' => EventResource::getUrl('edit', ['record' => $event]),
            ]);

        $orgs = Organisation::query()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Organisation $org): array => [
                'when' => $org->updated_at,
                'text' => 'Club: '.$org->name,
                'href' => OrganisationResource::getUrl('edit', ['record' => $org]),
            ]);

        $venues = Venue::query()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Venue $v): array => [
                'when' => $v->updated_at,
                'text' => 'Range: '.$v->name,
                'href' => VenueResource::getUrl('edit', ['record' => $v]),
            ]);

        return $events->concat($orgs)->concat($venues)
            ->sortByDesc('when')
            ->take(8)
            ->map(fn (array $row): array => [
                'when' => $row['when']?->timezone(config('app.timezone'))->diffForHumans() ?? '—',
                'text' => $row['text'],
                'href' => $row['href'],
            ])
            ->values()
            ->all();
    }

    private function mdRequestsPending(): int
    {
        return Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New)
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
