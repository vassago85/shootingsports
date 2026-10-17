<?php

namespace App\Filament\Desk\Resources\Events\Tables;

use App\Enums\EventStatus;
use App\Models\Event;
use Carbon\CarbonInterface;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('hostOrganisation.name')
                    ->label('Host')
                    ->searchable(),
                TextColumn::make('disciplines.name')
                    ->label('Disciplines')
                    ->badge(),
                TextColumn::make('starts_at')
                    ->formatStateUsing(fn ($state, Event $record): string => self::formatEventDate($state, $record))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('entry_fee_cents')
                    ->label('Fee')
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? 'R'.number_format($state / 100, 2)
                        : '—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(EventStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /**
     * All-day events show the date only — mirrors the admin table and
     * keeps the "02:00 phantom" out of the desk list too.
     */
    private static function formatEventDate(mixed $state, Event $record): string
    {
        if (! $state instanceof CarbonInterface) {
            return '—';
        }

        $local = $state->timezone(config('app.timezone'));

        return $record->all_day
            ? $local->format('D j M Y')
            : $local->format('D j M Y, H:i');
    }
}
