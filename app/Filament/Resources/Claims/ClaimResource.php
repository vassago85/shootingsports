<?php

namespace App\Filament\Resources\Claims;

use App\Enums\ClaimStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Resources\Claims\Pages\CreateClaim;
use App\Filament\Resources\Claims\Pages\EditClaim;
use App\Filament\Resources\Claims\Pages\ListClaims;
use App\Models\Claim;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('claimable_type')
                    ->required(),
                TextInput::make('claimable_id')
                    ->required()
                    ->numeric(),
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
                TextInput::make('reviewed_by')
                    ->numeric(),
                DateTimePicker::make('reviewed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claimable_type')
                    ->searchable(),
                TextColumn::make('claimable_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reviewed_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'create' => CreateClaim::route('/create'),
            'edit' => EditClaim::route('/{record}/edit'),
        ];
    }
}
