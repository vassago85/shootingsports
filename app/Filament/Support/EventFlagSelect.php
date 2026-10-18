<?php

namespace App\Filament\Support;

use App\Models\Event;
use App\Models\Flag;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EventFlagSelect
{
    /**
     * Admin form — relationship-backed checkboxes with definitions under
     * each option. Desk create uses {@see makeChips()} instead.
     */
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

    /**
     * Desk wizard / edit — chip-style toggles with definitions as tooltips
     * (mockup s4). Field state is a list of flag ids; pages that use this
     * must sync via {@see sync()} after save.
     */
    public static function makeChips(): ToggleButtons
    {
        return ToggleButtons::make('flags')
            ->label('Who this match is for')
            ->multiple()
            ->options(fn (): array => self::flagOptions()->all())
            ->tooltips(fn (): array => self::flagTooltips()->all())
            ->columns(3)
            ->columnSpanFull()
            ->helperText('Hover a chip for its definition. New shooter friendly is the public calendar filter.');
    }

    /**
     * @return Collection<int|string, string>
     */
    public static function flagOptions(): Collection
    {
        return Flag::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * @return Collection<int|string, string>
     */
    public static function flagTooltips(): Collection
    {
        return Flag::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('definition', 'id');
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
