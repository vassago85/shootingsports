<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Enums\VerificationState;
use App\Filament\Pages\VenueDuplicates;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "Find duplicates" was a standalone page in Phase 1. Phase 2
            // folds it into the Ranges list as a header action so the
            // merge screen is one click from the data it operates on.
            Action::make('findDuplicates')
                ->label('Find duplicates')
                ->icon(Heroicon::OutlinedSquare2Stack)
                ->color('gray')
                ->url(VenueDuplicates::getUrl()),
            CreateAction::make(),
        ];
    }

    /**
     * Verification tabs on Ranges — same shape as the Clubs list.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Unconfirmed))
                ->badge(Venue::query()->where('verification_state', VerificationState::Unconfirmed)->count()),
            'ageing' => Tab::make('Ageing')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Ageing))
                ->badge(Venue::query()->where('verification_state', VerificationState::Ageing)->count()),
            'verified' => Tab::make('Verified')
                ->modifyQueryUsing(fn (Builder $q): Builder => $q->where('verification_state', VerificationState::Verified)),
        ];
    }
}
