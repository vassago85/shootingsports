<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasQuietBreadcrumbs;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    use HasQuietBreadcrumbs;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * User tabs replace the "scan all 300 rows for the one with the
     * right flag" ritual. Staff / Match directors / Pending MD /
     * Unverified are the four buckets we triage against; All is the
     * escape hatch.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'staff' => Tab::make('Staff')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_staff', true))
                ->badge(User::query()->where('is_staff', true)->count()),
            'match_directors' => Tab::make('Match directors')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_match_director', true))
                ->badge(User::query()->where('is_match_director', true)->count()),
            'pending_md' => Tab::make('Pending MD')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('md_requested_at')
                    ->where('is_match_director', false)
                    ->whereNull('md_rejected_at'))
                ->badge(User::query()
                    ->whereNotNull('md_requested_at')
                    ->where('is_match_director', false)
                    ->whereNull('md_rejected_at')
                    ->count()),
            'unverified' => Tab::make('Unverified')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('email_verified_at'))
                ->badge(User::query()->whereNull('email_verified_at')->count()),
        ];
    }
}
