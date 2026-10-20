<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Enums\VerificationState;
use App\Filament\Concerns\HasQuietBreadcrumbs;
use App\Filament\Resources\Providers\ProviderResource;
use App\Models\Provider;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProviders extends ListRecords
{
    use HasQuietBreadcrumbs;

    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Verification tabs on Industry — same shape as the Clubs list.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $ageingCount = Provider::query()->where('verification_state', VerificationState::Ageing)->count();

        $tabs = [
            'all' => Tab::make('All'),
            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('verification_state', VerificationState::Unconfirmed))
                ->badge(Provider::query()->where('verification_state', VerificationState::Unconfirmed)->count()),
        ];

        if ($ageingCount > 0) {
            $tabs['ageing'] = Tab::make('Ageing')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('verification_state', VerificationState::Ageing))
                ->badge($ageingCount);
        }

        $tabs['verified'] = Tab::make('Verified')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('verification_state', VerificationState::Verified));

        return $tabs;
    }
}
