<?php

namespace App\Filament\Resources\Organisations\RelationManagers;

use App\Enums\OrganisationUserRole;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Match directors & members';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->options(OrganisationUserRole::class)
                    ->required()
                    ->default(OrganisationUserRole::MatchDirector->value),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('A person can be a match director on many clubs and federations (e.g. PPRC and SAPRF). A listing can have several match directors.')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof OrganisationUserRole) {
                            return $state->getLabel();
                        }

                        return OrganisationUserRole::tryFrom((string) $state)?->getLabel() ?? (string) $state;
                    }),
                TextColumn::make('granted_at')
                    ->dateTime('j M Y')
                    ->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add person')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options(OrganisationUserRole::class)
                            ->required()
                            ->default(OrganisationUserRole::MatchDirector->value),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['granted_by'] = Auth::id();
                        $data['granted_at'] = now();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Select::make('role')
                            ->options(OrganisationUserRole::class)
                            ->required(),
                    ]),
                DetachAction::make()->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Remove selected'),
                ]),
            ]);
    }
}
