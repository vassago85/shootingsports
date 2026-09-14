<?php

namespace App\Filament\Support;

use App\Models\Event;
use App\Models\Flag;
use Filament\Forms\Components\CheckboxList;

class EventFlagSelect
{
    public static function make(): CheckboxList
    {
        return CheckboxList::make('flag_ids')
            ->label('Who this match is for')
            ->columns(2)
            ->options(fn (): array => Flag::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->descriptions(fn (): array => Flag::query()
                ->pluck('definition', 'id')
                ->all())
            ->helperText('Tick New shooter friendly if a first-timer can shoot without slowing the squad. That is the public calendar filter.')
            ->columnSpanFull();
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function sync(Event $event, array $ids): void
    {
        $event->flags()->sync(array_values(array_filter(array_map(intval(...), $ids))));
    }

    /**
     * @return list<int>
     */
    public static function idsFor(Event $event): array
    {
        return $event->flags()->pluck('flags.id')->all();
    }
}
