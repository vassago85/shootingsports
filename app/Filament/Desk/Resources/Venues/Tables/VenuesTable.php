<?php

namespace App\Filament\Desk\Resources\Venues\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('media')
                    ->circular()
                    ->defaultImageUrl(null)
                    ->height(36),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('province')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('town')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('access')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? $state),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? $state)
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'published' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('verification_state')
                    ->label('Verification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? $state)
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
