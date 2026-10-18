<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Enums\VerificationState;
use App\Filament\Concerns\HasQuietBreadcrumbs;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Models\Organisation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrganisations extends ListRecords
{
    use HasQuietBreadcrumbs;

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
        $ageingCount = Organisation::query()->where('verification_state', VerificationState::Ageing)->count();

        $tabs = [
            'all' => Tab::make('All'),
            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Unconfirmed))
                ->badge(Organisation::query()->where('verification_state', VerificationState::Unconfirmed)->count()),
        ];

        // Ageing is almost always zero on a healthy register — hide it
        // so staff do not have to look at an empty tab every time.
        if ($ageingCount > 0) {
            $tabs['ageing'] = Tab::make('Ageing')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Ageing))
                ->badge($ageingCount);
        }

        $tabs['verified'] = Tab::make('Verified')
            ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Verified));

        return $tabs;
    }
}
