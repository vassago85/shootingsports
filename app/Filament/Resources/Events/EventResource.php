<?php

namespace App\Filament\Resources\Events;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Calendar';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Select::make('host_organisation_id')
                    ->relationship('hostOrganisation', 'name')
                    ->required(),
                Select::make('venue_id')
                    ->relationship('venue', 'name'),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at'),
                Toggle::make('all_day')
                    ->required(),
                Select::make('level')
                    ->options(EventLevel::class)
                    ->default('club')
                    ->required(),
                Select::make('status')
                    ->options(EventStatus::class)
                    ->default('draft')
                    ->required(),
                DateTimePicker::make('confirmed_at'),
                DateTimePicker::make('original_starts_at'),
                TextInput::make('entry_fee_cents')
                    ->numeric(),
                TextInput::make('member_fee_cents')
                    ->numeric(),
                TextInput::make('entry_url')
                    ->url(),
                TextInput::make('capacity')
                    ->numeric(),
                TextInput::make('entries_taken')
                    ->numeric(),
                TextInput::make('round_count')
                    ->numeric(),
                TextInput::make('target_count')
                    ->numeric(),
                TextInput::make('stage_count')
                    ->numeric(),
                TextInput::make('results_url')
                    ->url(),
                TextInput::make('banner_media_id')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric(),
                Select::make('source')
                    ->options(ListingSource::class)
                    ->default('staff')
                    ->required(),
                DateTimePicker::make('last_verified_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('hostOrganisation.name')
                    ->searchable(),
                TextColumn::make('venue.name')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('all_day')
                    ->boolean(),
                TextColumn::make('level')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('original_starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('entry_fee_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('member_fee_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entry_url')
                    ->searchable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entries_taken')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('round_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('target_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stage_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('results_url')
                    ->searchable(),
                TextColumn::make('banner_media_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->searchable(),
                TextColumn::make('last_verified_at')
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
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(EventStatus::class),
                SelectFilter::make('level')->options(EventLevel::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('confirm')
                        ->label('Confirm dates')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->confirm();
                            Notification::make()->title('Events confirmed.')->success()->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
