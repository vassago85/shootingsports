<?php

namespace App\Filament\Pages;

use App\Enums\ClaimStatus;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ModerationStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Desk\Resources\Articles\ArticleResource;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Article;
use App\Models\Claim;
use App\Models\Enquiry;
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
    public string $activeTab = 'enquiries';

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
     * @return array<string, array{label: string, count: int}>
     */
    public function getTabs(): array
    {
        return [
            'enquiries' => ['label' => 'Enquiries', 'count' => $this->enquiriesQuery()->count()],
            'submissions' => ['label' => 'Submissions', 'count' => $this->submissionsQuery()->count()],
            'claims' => ['label' => 'Claims', 'count' => $this->claimsQuery()->count()],
            'media' => ['label' => 'Media', 'count' => $this->mediaQuery()->count()],
            'director_requests' => ['label' => 'Director requests', 'count' => $this->directorRequestsQuery()->count()],
            'articles' => ['label' => 'Articles', 'count' => $this->articlesQuery()->count()],
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
        return match ($this->activeTab) {
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

    // -- Badge support ----------------------------------------------------

    public static function countOpen(): int
    {
        return Enquiry::query()->where('status', EnquiryStatus::New)->count()
            + Submission::query()->where('status', SubmissionStatus::Pending)->count()
            + Claim::query()->where('status', ClaimStatus::Pending)->count()
            + Media::query()->where('moderation_status', ModerationStatus::Pending)->count()
            + Article::query()->whereNull('published_at')->count();
    }

    private static function hasStaleItem(): bool
    {
        $threshold = now()->subDays(3);

        return Enquiry::query()->where('status', EnquiryStatus::New)->where('created_at', '<', $threshold)->exists()
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
