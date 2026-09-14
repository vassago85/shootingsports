<?php

namespace App\Filament\Support;

use App\Models\Flag;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Database\Eloquent\Builder;

class EventFlagSelect
{
    public static function make(): CheckboxList
    {
        return CheckboxList::make('flags')
            ->label('Who this match is for')
            ->relationship(
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            )
            ->getOptionDescriptionFromRecordUsing(fn (Flag $record): string => $record->definition)
            ->columns(2)
            ->helperText('Tick New shooter friendly if a first-timer can shoot without slowing the squad. That is the public calendar filter.')
            ->columnSpanFull();
    }
}
