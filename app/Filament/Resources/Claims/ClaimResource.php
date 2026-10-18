<?php

namespace App\Filament\Resources\Claims;

use App\Enums\ClaimStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Resources\Claims\Pages\EditClaim;
use App\Filament\Resources\Claims\Pages\ListClaims;
use App\Models\Claim;
use App\Support\RecordLinker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = null;

    /**
     * Claims are reached through the Inbox tab now, not the top-level nav.
     * The resource routes still resolve for deep links.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        // Claims come from the public /claim form. Staff never create one.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('claimable')
                    ->label('Record')
                    ->content(fn (?Claim $record): string => RecordLinker::label($record?->claimable)),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Textarea::make('evidence')
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ClaimStatus::class)
                    ->default('pending')
                    ->required(),
                Select::make('reviewed_by')
                    ->relationship(
                        name: 'reviewer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('is_staff', true),
                    )
                    ->default(fn (): ?int => auth()->user()?->is_staff ? auth()->id() : null)
                    ->searchable()
                    ->preload(),
                DateTimePicker::make('reviewed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('claimable')
                    ->label('Record')
                    ->getStateUsing(fn (Claim $record): string => RecordLinker::label($record->claimable))
                    ->url(fn (Claim $record): ?string => RecordLinker::adminUrl($record->claimable))
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Claimed by')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ClaimStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->visible(fn (Claim $record): bool => $record->status === ClaimStatus::Pending)
                    ->schema(function (Claim $record): array {
                        if ($record->claimable_type !== 'organisation') {
                            return [];
                        }

                        return [
                            Select::make('role')
                                ->options(OrganisationUserRole::class)
                                ->default(OrganisationUserRole::Admin->value)
                                ->required(),
                        ];
                    })
                    ->requiresConfirmation()
                    ->action(function (Claim $record, array $data): void {
                        $role = OrganisationUserRole::tryFrom((string) ($data['role'] ?? '')) ?? OrganisationUserRole::Admin;

                        $record->approve(auth()->user(), $role);
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn (Claim $record): bool => $record->status === ClaimStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (Claim $record) => $record->reject(auth()->user())),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClaims::route('/'),
            'edit' => EditClaim::route('/{record}/edit'),
        ];
    }
}
