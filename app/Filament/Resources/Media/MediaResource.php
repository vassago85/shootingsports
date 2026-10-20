<?php

namespace App\Filament\Resources\Media;

use App\Enums\MediaRole;
use App\Enums\ModerationStatus;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Support\Inbox\AgeLabel;
use App\Support\RecordLinker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = null;

    /**
     * Media is reached through the Inbox tab now, not the top-level nav.
     * The resource routes still resolve for deep links.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        // Media rows are created when a user uploads via the public
        // forms. Staff only moderate.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('attachable')
                    ->label('Attached to')
                    ->content(fn (?Media $record): string => RecordLinker::label($record?->attachable)),
                Placeholder::make('path')
                    ->label('File')
                    ->content(fn (?Media $record): string => (string) $record?->path),
                Placeholder::make('mime')
                    ->label('Type')
                    ->content(fn (?Media $record): string => (string) $record?->mime),
                Select::make('role')
                    ->options(MediaRole::class)
                    ->required(),
                Select::make('uploaded_by')
                    ->relationship('uploader', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('moderation_status')
                    ->options(ModerationStatus::class)
                    ->default('pending')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('attachable')
                    ->label('Attached to')
                    ->getStateUsing(fn (Media $record): string => RecordLinker::label($record->attachable))
                    ->url(fn (Media $record): ?string => RecordLinker::adminUrl($record->attachable))
                    ->wrap(),
                TextColumn::make('role')
                    ->badge()
                    ->searchable(),
                TextColumn::make('moderation_status')
                    ->label('Moderation')
                    ->badge()
                    ->searchable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('age')
                    ->label('Age')
                    ->getStateUsing(fn (Media $record): string => AgeLabel::for($record->created_at)),
                // Storage internals are hidden by default — staff moderate
                // based on the attached record and the role, not the
                // disk/path/dimensions.
                TextColumn::make('disk')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('path')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mime')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : self::formatBytes($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('width')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('height')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('moderation_status')->options(ModerationStatus::class)->default('pending'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->visible(fn (Media $record): bool => $record->moderation_status === ModerationStatus::Pending)
                    ->action(fn (Media $record) => $record->forceFill(['moderation_status' => ModerationStatus::Approved])->save()),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn (Media $record): bool => $record->moderation_status === ModerationStatus::Pending)
                    ->action(fn (Media $record) => $record->forceFill(['moderation_status' => ModerationStatus::Rejected])->save()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->action(fn (Collection $records) => $records->each->forceFill(['moderation_status' => ModerationStatus::Approved])->each->save()),
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
            'index' => ListMedia::route('/'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
