<?php

namespace App\Filament\Resources\Events\Pages;

use App\Enums\EventStatus;
use App\Filament\Concerns\HasQuietBreadcrumbs;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    use HasQuietBreadcrumbs;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    /**
     * The Upcoming tab is what staff see first — matches a shooter can
     * still enter, soonest first. Needs attention surfaces importer
     * flags plus the usual missing-data scopes. Past and All are the
     * escape hatches.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            // The admin Upcoming tab is wider than the public upcoming()
            // scope — staff must see orphan matches, drafts and matches
            // with a non-published host so they can triage them. Public
            // "upcoming" has its own filter (on_public_calendar) for the
            // narrower, shooter-facing view.
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $window): void {
                        $window->where('starts_at', '>=', now())
                            ->orWhere(function (Builder $running): void {
                                $running->whereNotNull('ends_at')
                                    ->where('ends_at', '>=', now());
                            });
                    })
                    ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Completed])
                    ->reorder('starts_at', 'asc'))
                ->badge(Event::query()->upcoming()->count()),

            'needs_attention' => Tab::make('Needs attention')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $window): void {
                        $window->where('needs_review', true)
                            ->orWhereIn('id', Event::query()->missingOrganiser()->select('id'))
                            ->orWhereIn('id', Event::query()->missingRange()->select('id'))
                            ->orWhereIn('id', Event::query()->missingSport()->select('id'))
                            ->orWhereIn('id', Event::query()->missingRegistrationLink()->select('id'));
                    })
                    ->reorder('starts_at', 'asc'))
                ->badge(self::needsAttentionCount()),

            'past' => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('starts_at', '<', now())
                    ->reorder('starts_at', 'desc')),

            'all' => Tab::make('All'),
        ];
    }

    private static function needsAttentionCount(): int
    {
        $flagged = Event::query()->where('needs_review', true)->count();
        $missingOrganiser = Event::query()->missingOrganiser()->count();
        $missingRange = Event::query()->missingRange()->count();
        $missingSport = Event::query()->missingSport()->count();
        $missingLink = Event::query()->missingRegistrationLink()->count();

        // Deliberately a loose sum — the exact tab count is the real
        // source of truth once the staff member opens it. The badge
        // only needs to tell them "there is something here".
        return $flagged + $missingOrganiser + $missingRange + $missingSport + $missingLink;
    }
}
