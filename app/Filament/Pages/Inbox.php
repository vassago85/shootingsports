<?php

namespace App\Filament\Pages;

use App\Enums\ClaimStatus;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\ModerationStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Desk\Resources\Articles\ArticleResource;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Article;
use App\Models\Claim;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * One queue for every piece of work that reached staff through the
 * public site: Enquiries, Submissions (corrections), Claims (listing
 * ownership), Media (user uploads), Director requests (users asking
 * for /desk) and Articles (media-partner drafts).
 *
 * Each tab reuses the resource's static table() so columns, filters
 * and row actions stay in one place. Switching tabs just swaps the
 * table definition + query — no 6 separate component classes.
 */
class Inbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.inbox';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Inbox';

    protected static ?string $navigationLabel = 'Inbox';

    #[Url(as: 'tab')]
    public string $activeTab = 'all';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_staff;
    }

    public static function getNavigationBadge(): ?string
    {
        $total = self::countOpen();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Danger when anything in the queue is older than 3 days —
        // mirrors the spec's "colour danger when any item is older than
        // 3 days" rule so staff cannot ignore a stale queue.
        return self::hasStaleItem() ? 'danger' : 'warning';
    }

    /**
     * Tab order matches the mockup (All first, then the per-type tabs
     * in rough frequency order). The `submissions` key is kept for
     * bookmark/URL compatibility — the user-visible label is
     * "Corrections", which is what staff actually call these.
     *
     * @return array<string, array{label: string, count: int}>
     */
    public function getTabs(): array
    {
        return [
            'all' => ['label' => 'All', 'count' => self::countOpen()],
            'events' => ['label' => 'Events', 'count' => $this->eventsQuery()->count()],
            'director_requests' => ['label' => 'Director requests', 'count' => $this->directorRequestsQuery()->count()],
            'claims' => ['label' => 'Claims', 'count' => $this->claimsQuery()->count()],
            'submissions' => ['label' => 'Corrections', 'count' => $this->submissionsQuery()->count()],
            'media' => ['label' => 'Media', 'count' => $this->mediaQuery()->count()],
            'articles' => ['label' => 'Articles', 'count' => $this->articlesQuery()->count()],
            'enquiries' => ['label' => 'Enquiries', 'count' => $this->enquiriesQuery()->count()],
        ];
    }

    public function setActiveTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getTabs())) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $this->tableForActiveTab($table)
            ->emptyStateHeading($this->emptyStateHeading())
            ->emptyStateDescription('Nothing waiting here. Check the other tabs or come back later.')
            ->emptyStateIcon(Heroicon::OutlinedInboxStack);
    }

    /**
     * The "All" tab is a merged feed across every queue; it is not a
     * Filament table because the rows come from six different models.
     * The blade view branches on `$activeTab === 'all'` and renders a
     * light-weight table from this collection instead of `$this->table`.
     *
     * @return Collection<int, array{type: string, label: string, color: string, what: string, context: ?string, from: ?string, created_at: CarbonInterface, open_url: ?string}>
     */
    public function getAllItems(): Collection
    {
        $items = collect();

        // Events waiting for staff approval. The "awaiting_approval"
        // filter on the Events list uses the same shape, so clicking
        // the row lands on exactly the same row in the Events table.
        $items = $items->merge($this->eventsQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Event $event): array => [
                'type' => 'event',
                'label' => 'EVENT',
                'color' => 'info',
                'what' => $event->title,
                'context' => $event->hostOrganisation?->name,
                'from' => null,
                'created_at' => $event->created_at,
                'open_url' => EventResource::getUrl('edit', ['record' => $event]),
            ]));

        $items = $items->merge($this->directorRequestsQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Enquiry $enquiry): array => [
                'type' => 'director_request',
                'label' => 'DIRECTOR',
                'color' => 'warning',
                'what' => 'Wants /desk access',
                'context' => $enquiry->body,
                'from' => $enquiry->name,
                'created_at' => $enquiry->created_at,
                'open_url' => EnquiryResource::getUrl('edit', ['record' => $enquiry]),
            ]));

        $items = $items->merge($this->claimsQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Claim $claim): array => [
                'type' => 'claim',
                'label' => 'CLAIM',
                'color' => 'warning',
                'what' => 'Claim on '.($claim->claimable?->name ?? 'listing'),
                'context' => $claim->evidence,
                'from' => $claim->user?->name,
                'created_at' => $claim->created_at,
                'open_url' => ClaimResource::getUrl('edit', ['record' => $claim]),
            ]));

        $items = $items->merge($this->submissionsQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Submission $submission): array => [
                'type' => 'submission',
                'label' => 'CORRECTION',
                'color' => 'gray',
                'what' => ucfirst((string) $submission->type).' correction',
                'context' => null,
                'from' => $submission->submitter_name,
                'created_at' => $submission->created_at,
                'open_url' => SubmissionResource::getUrl('edit', ['record' => $submission]),
            ]));

        $items = $items->merge($this->mediaQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Media $media): array => [
                'type' => 'media',
                'label' => 'MEDIA',
                'color' => 'gray',
                'what' => $media->path,
                'context' => null,
                'from' => null,
                'created_at' => $media->created_at,
                'open_url' => MediaResource::getUrl('edit', ['record' => $media]),
            ]));

        $items = $items->merge($this->articlesQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Article $article): array => [
                'type' => 'article',
                'label' => 'ARTICLE',
                'color' => 'gray',
                'what' => $article->title,
                'context' => null,
                'from' => $article->user?->name,
                'created_at' => $article->created_at,
                'open_url' => ArticleResource::getUrl('edit', ['record' => $article]),
            ]));

        $items = $items->merge($this->enquiriesQuery()->oldest('created_at')->limit(25)->get()
            ->map(fn (Enquiry $enquiry): array => [
                'type' => 'enquiry',
                'label' => 'ENQUIRY',
                'color' => 'gray',
                'what' => $enquiry->subject ?: 'Enquiry',
                'context' => $enquiry->body,
                'from' => $enquiry->name,
                'created_at' => $enquiry->created_at,
                'open_url' => EnquiryResource::getUrl('edit', ['record' => $enquiry]),
            ]));

        return $items
            ->sortBy(fn (array $row): int => $row['created_at']->getTimestamp())
            ->values()
            ->take(50);
    }

    public function ageLabelPublic(?CarbonInterface $when): string
    {
        return self::ageLabel($when);
    }

    private function tableForActiveTab(Table $table): Table
    {
        return match ($this->activeTab) {
            'all' => $this->emptyTableForAllTab($table),
            'events' => EventResource::table($table)
                ->query(fn (): Builder => $this->eventsQuery())
                ->defaultSort('created_at', 'asc'),
            'submissions' => SubmissionResource::table($table)
                ->query(fn (): Builder => $this->submissionsQuery())
                ->defaultSort('created_at', 'asc'),
            'claims' => ClaimResource::table($table)
                ->query(fn (): Builder => $this->claimsQuery())
                ->defaultSort('created_at', 'asc'),
            'media' => MediaResource::table($table)
                ->query(fn (): Builder => $this->mediaQuery())
                ->defaultSort('created_at', 'asc'),
            'director_requests' => EnquiryResource::table($table)
                ->query(fn (): Builder => $this->directorRequestsQuery())
                ->defaultSort('created_at', 'asc'),
            'articles' => $this->articlesTable($table),
            default => EnquiryResource::table($table)
                ->query(fn (): Builder => $this->enquiriesQuery())
                ->defaultSort('created_at', 'asc'),
        };
    }

    /**
     * HasTable requires a Table even when the "All" tab renders its
     * own blade instead. Return the enquiries table scoped to nothing
     * so Filament has a valid component to mount; the blade hides it
     * when `$activeTab === 'all'`.
     */
    private function emptyTableForAllTab(Table $table): Table
    {
        return EnquiryResource::table($table)
            ->query(fn (): Builder => Enquiry::query()->whereRaw('1 = 0'));
    }

    /**
     * Human empty-state heading for the active tab. Rendered when the
     * table has no rows, so staff see "No enquiries waiting" instead
     * of ten bare column headers.
     */
    private function emptyStateHeading(): string
    {
        return match ($this->activeTab) {
            'all' => 'Inbox is clear',
            'events' => 'No events waiting for approval',
            'submissions' => 'No corrections waiting',
            'claims' => 'No listing claims waiting',
            'media' => 'No media uploads waiting',
            'director_requests' => 'No director requests waiting',
            'articles' => 'No draft articles waiting',
            default => 'No enquiries waiting',
        };
    }

    /**
     * Articles live in the Desk panel so the admin Inbox tab builds
     * its own lightweight review table rather than reaching across
     * panels. Phase 3 brings a proper media-partner review flow —
     * for Phase 2, "pending" means `published_at is null`.
     */
    private function articlesTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->articlesQuery())
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->date('j M Y')->sortable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('user.name')->label('Author'),
                TextColumn::make('age')
                    ->label('Age')
                    ->getStateUsing(fn (Article $record): string => self::ageLabel($record->created_at)),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish now')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Article $record) => $record->update(['published_at' => now()])),
                Action::make('open')
                    ->label('Open')
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record]), shouldOpenInNewTab: true),
            ])
            ->defaultSort('created_at', 'asc');
    }

    // -- Queries ----------------------------------------------------------

    private function enquiriesQuery(): Builder
    {
        return Enquiry::query()
            ->where('type', '!=', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New);
    }

    private function submissionsQuery(): Builder
    {
        return Submission::query()->where('status', SubmissionStatus::Pending);
    }

    private function claimsQuery(): Builder
    {
        return Claim::query()->where('status', ClaimStatus::Pending);
    }

    private function mediaQuery(): Builder
    {
        return Media::query()->where('moderation_status', ModerationStatus::Pending);
    }

    private function directorRequestsQuery(): Builder
    {
        return Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New);
    }

    private function articlesQuery(): Builder
    {
        return Article::query()->whereNull('published_at');
    }

    private function eventsQuery(): Builder
    {
        // Events waiting for staff review. Pre-Phase B this is just
        // Draft events — Phase B will introduce a proper
        // `events.publication = pending_review` state that supersedes
        // this and keeps "drafts I'm still writing" separate from
        // "staff please publish".
        return Event::query()->where('status', EventStatus::Draft);
    }

    // -- Badge support ----------------------------------------------------

    /**
     * Must stay in lock-step with the sum of the per-tab counts in
     * {@see self::getTabs()} — the nav badge should never disagree
     * with the sum of the tab badges. Enquiries excludes MD-signup
     * (counted separately under "Director requests") because the
     * Enquiries tab query filters MD-signup out.
     */
    public static function countOpen(): int
    {
        return Enquiry::query()
            ->where('type', '!=', EnquiryType::MdSignup)
            ->where('status', EnquiryStatus::New)
            ->count()
            + Event::query()->where('status', EventStatus::Draft)->count()
            + Submission::query()->where('status', SubmissionStatus::Pending)->count()
            + Claim::query()->where('status', ClaimStatus::Pending)->count()
            + Media::query()->where('moderation_status', ModerationStatus::Pending)->count()
            + Enquiry::query()
                ->where('type', EnquiryType::MdSignup)
                ->where('status', EnquiryStatus::New)
                ->count()
            + Article::query()->whereNull('published_at')->count();
    }

    private static function hasStaleItem(): bool
    {
        $threshold = now()->subDays(3);

        return Enquiry::query()->where('status', EnquiryStatus::New)->where('created_at', '<', $threshold)->exists()
            || Event::query()->where('status', EventStatus::Draft)->where('created_at', '<', $threshold)->exists()
            || Submission::query()->where('status', SubmissionStatus::Pending)->where('created_at', '<', $threshold)->exists()
            || Claim::query()->where('status', ClaimStatus::Pending)->where('created_at', '<', $threshold)->exists()
            || Media::query()->where('moderation_status', ModerationStatus::Pending)->where('created_at', '<', $threshold)->exists()
            || Article::query()->whereNull('published_at')->where('created_at', '<', $threshold)->exists();
    }

    private static function ageLabel(?CarbonInterface $when): string
    {
        if ($when === null) {
            return '—';
        }

        $days = (int) $when->diffInDays(now());

        return match (true) {
            $days === 0 => 'today',
            $days === 1 => '1 day',
            default => "{$days} days",
        };
    }
}
