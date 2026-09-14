<?php

namespace App\Filament\Support;

use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class EventDisciplineSelect
{
    public static function make(): Select
    {
        return Select::make('discipline_ids')
            ->label('Disciplines')
            ->multiple()
            ->searchable()
            ->preload()
            ->required()
            ->options(fn (): array => self::groupedOptions())
            ->helperText('Every discipline this match covers. The first one is the primary shown on cards.')
            ->columnSpanFull();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(): array
    {
        return Discipline::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Discipline $discipline): string => $discipline->family->getLabel())
            ->map(fn ($group) => $group->pluck('name', 'id')->all())
            ->all();
    }

    public static function prefillFromHost(): \Closure
    {
        return function (mixed $state, Set $set, Get $get): void {
            if (blank($state) || filled($get('discipline_ids'))) {
                return;
            }

            $ids = Organisation::query()
                ->find($state)
                ?->disciplines()
                ->pluck('disciplines.id')
                ->all() ?? [];

            if ($ids !== []) {
                $set('discipline_ids', $ids);
            }
        };
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function sync(Event $event, array $ids): void
    {
        $ids = array_values(array_filter(array_map(intval(...), $ids)));

        $event->syncDisciplines($ids, $ids[0] ?? null);
    }

    /**
     * @return list<int>
     */
    public static function idsFor(Event $event): array
    {
        return $event->disciplines()->pluck('disciplines.id')->all();
    }
}
