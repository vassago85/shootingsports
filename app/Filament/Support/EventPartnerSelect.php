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
                    ->orderBy('name'),
            )
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Published industry listings shown on the match page as sponsors.')
            ->columnSpanFull();
    }
}
