<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class EventPartnerSelect
{
    public static function make(): Select
    {
        return Select::make('partners')
            ->label('Supplier partners')
            ->relationship(
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->published()
                    ->listed()
                    // The options query is SELECT DISTINCT providers.*, and
                    // Postgres has no equality operator for the json `services`
                    // column, so the event edit page 500s. id and name are enough
                    // for the picker, and the pivot sort column must not stay in
                    // ORDER BY once it is no longer selected.
                    ->reorder()
                    ->orderBy('providers.name')
                    ->select(['providers.id', 'providers.name']),
            )
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Published industry listings shown on the match page as sponsors.')
            ->columnSpanFull();
    }
}
