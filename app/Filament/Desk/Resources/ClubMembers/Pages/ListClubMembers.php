<?php

namespace App\Filament\Desk\Resources\ClubMembers\Pages;

use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Filament\Resources\Organisations\Pages\ListOrganisations;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClubMembers extends ListRecords
{
    protected static string $resource = ClubMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add member'),
        ];
    }

    /**
     * Workflow tabs along the top of the members list. Mirrors the
     * {@see ListOrganisations}
     * pattern (All first, then condition-specific slices with badge
     * counts) so one-click switching between the four day-to-day
     * views — Current / Applied / Lapsed / Ex-members — replaces
     * repeatedly cycling the status filter dropdown.
     *
     * `Expiring soon` only appears when there are rows to show;
     * similarly, `Ex-members` hides itself on a clean register so
     * admins don't stare at an empty tab every time.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $scoped = fn (): Builder => $this->getResource()::getEloquentQuery();

        $appliedCount = (clone $scoped())
            ->where('status', ClubMemberStatus::Applied)
            ->count();

        $expiringCount = (clone $scoped())
            ->expiringWithin(30)
            ->count();

        $exMemberCount = (clone $scoped())
            ->whereIn('status', array_map(
                fn (ClubMemberStatus $status): string => $status->value,
                ClubMemberStatus::exMemberStates(),
            ))
            ->count();

        $tabs = [
            'all' => Tab::make('All'),
            'current' => Tab::make('Current')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->active()),
        ];

        if ($appliedCount > 0) {
            $tabs['applied'] = Tab::make('Applied')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ClubMemberStatus::Applied))
                ->badge($appliedCount)
                ->badgeColor('warning');
        }

        $tabs['lapsed'] = Tab::make('Lapsed')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->lapsed());

        if ($expiringCount > 0) {
            $tabs['expiring'] = Tab::make('Expiring in 30 days')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->expiringWithin(30))
                ->badge($expiringCount);
        }

        if ($exMemberCount > 0) {
            $tabs['ex_members'] = Tab::make('Ex-members')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', array_map(
                    fn (ClubMemberStatus $status): string => $status->value,
                    ClubMemberStatus::exMemberStates(),
                )))
                ->badge($exMemberCount)
                ->badgeColor('gray');
        }

        return $tabs;
    }
}
