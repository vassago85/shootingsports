<?php

namespace App\Filament\Desk\Resources\Events\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Enums\EventStatus;

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
                TextColumn::make('starts_at')
                    ->dateTime('D j M Y, H:i')
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
}
