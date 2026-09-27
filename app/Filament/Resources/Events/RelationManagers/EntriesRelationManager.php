<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\EntryStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Entries';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Shooter')->searchable(),
                TextColumn::make('user.email')->label('Email'),
                TextColumn::make('division')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->label('Payment')->badge(),
                TextColumn::make('created_at')->dateTime('j M Y H:i')->label('Entered'),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->where('status', '!=', EntryStatus::Withdrawn->value));
    }
}
