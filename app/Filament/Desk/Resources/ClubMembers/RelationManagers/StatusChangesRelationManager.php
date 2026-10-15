<?php

namespace App\Filament\Desk\Resources\ClubMembers\RelationManagers;

use App\Enums\ClubMemberStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusChanges';

    protected static ?string $title = 'History';

    public function form(Schema $schema): Schema
    {
        // Read-only — history rows are written by the resource actions.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('j M Y H:i')->label('When'),
                TextColumn::make('from_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ClubMemberStatus
                        ? $state->getLabel()
                        : (ClubMemberStatus::tryFrom((string) $state)?->getLabel() ?? '—')),
                TextColumn::make('to_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ClubMemberStatus
                        ? $state->getLabel()
                        : (ClubMemberStatus::tryFrom((string) $state)?->getLabel() ?? '—')),
                TextColumn::make('changedBy.name')->label('By')->placeholder('—'),
                TextColumn::make('reason')->wrap()->placeholder('—'),
            ]);
    }
}
