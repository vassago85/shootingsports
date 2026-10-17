<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Enums\VerificationState;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Models\Organisation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrganisations extends ListRecords
{
    protected static string $resource = OrganisationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Verification tabs replace the standalone VerificationDashboard
     * for Clubs — Unconfirmed first so staff see what's waiting on
     * them, Ageing to catch listings that need a nudge, Verified for
     * the clean set, All as the escape hatch.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Unconfirmed))
                ->badge(Organisation::query()->where('verification_state', VerificationState::Unconfirmed)->count()),
            'ageing' => Tab::make('Ageing')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Ageing))
                ->badge(Organisation::query()->where('verification_state', VerificationState::Ageing)->count()),
            'verified' => Tab::make('Verified')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Verified)),
        ];
    }
}
