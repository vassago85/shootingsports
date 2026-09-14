<?php

namespace App\Filament\Resources\Media;

use App\Enums\MediaRole;
use App\Enums\ModerationStatus;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('disk')
                    ->required(),
                TextInput::make('path')
                    ->required(),
                TextInput::make('mime')
                    ->required(),
                TextInput::make('bytes')
                    ->required()
                    ->numeric(),
                TextInput::make('width')
                    ->numeric(),
                TextInput::make('height')
                    ->numeric(),
                TextInput::make('attachable_type'),
                TextInput::make('attachable_id')
                    ->numeric(),
                Select::make('role')
                    ->options(MediaRole::class)
                    ->required(),
                Textarea::make('derivatives')
                    ->columnSpanFull(),
                TextInput::make('uploaded_by')
                    ->numeric(),
                Select::make('moderation_status')
                    ->options(ModerationStatus::class)
                    ->default('pending')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('disk')
                    ->searchable(),
                TextColumn::make('path')
                    ->searchable(),
                TextColumn::make('mime')
                    ->searchable(),
                TextColumn::make('bytes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('width')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('height')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('attachable_type')
                    ->searchable(),
                TextColumn::make('attachable_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->searchable(),
                TextColumn::make('uploaded_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('moderation_status')
                    ->badge()
                    ->searchable(),
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
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
