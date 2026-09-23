<?php

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email log';

    protected static ?string $modelLabel = 'email';

    protected static ?string $pluralModelLabel = 'email log';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sent_at')->dateTime('j M Y, H:i'),
                TextEntry::make('from')->placeholder('—'),
                TextEntry::make('to'),
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('body')->columnSpanFull()->placeholder('No body'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sent_at', 'desc')
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('to')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('subject')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('from')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailLogs::route('/'),
        ];
    }
}
