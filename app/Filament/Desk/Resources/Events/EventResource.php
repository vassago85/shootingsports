<?php

namespace App\Filament\Desk\Resources\Events;

use App\Filament\Desk\Resources\Events\Pages\CreateEvent;
use App\Filament\Desk\Resources\Events\Pages\EditEvent;
use App\Filament\Desk\Resources\Events\Pages\ListEvents;
use App\Filament\Desk\Resources\Events\Schemas\EventForm;
use App\Filament\Desk\Resources\Events\Tables\EventsTable;
use App\Filament\Resources\Events\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\Events\RelationManagers\ResultsRelationManager;
use App\Models\Event;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Calendar';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'event';

    protected static ?string $pluralModelLabel = 'events';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        $orgIds = $user?->organisations()->pluck('organisations.id') ?? collect();

        return $query->whereIn('host_organisation_id', $orgIds);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->is_match_director);
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
            ResultsRelationManager::class,
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
}
