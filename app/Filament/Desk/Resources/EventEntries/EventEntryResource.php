<?php

namespace App\Filament\Desk\Resources\EventEntries;

use App\Enums\EntryStatus;
use App\Filament\Desk\Resources\EventEntries\Pages\ManageEventEntries;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Director desk — platform entries across clubs this person manages.
 * Shown in the nav only when at least one of their events takes
 * on-site entries (mockup s3).
 */
class EventEntryResource extends Resource
{
    protected static ?string $model = EventEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Your clubs';

    protected static ?string $navigationLabel = 'Entries';

    protected static ?string $modelLabel = 'entry';

    protected static ?string $pluralModelLabel = 'entries';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Shooter')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('division')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Entered')
                    ->dateTime('j M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', '!=', EntryStatus::Withdrawn->value))
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('event_id', self::managedEventIds());
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isDirectorDeskUser()) {
            return false;
        }

        $orgIds = $user->organisations()->pluck('organisations.id');

        if ($orgIds->isEmpty()) {
            return false;
        }

        return Event::query()
            ->whereIn('host_organisation_id', $orgIds)
            ->where(function (Builder $query): void {
                $query->where('accepts_platform_entries', true)
                    ->orWhere('accepts_team_entries', true);
            })
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEventEntries::route('/'),
        ];
    }

    /**
     * @return list<int>
     */
    protected static function managedEventIds(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $orgIds = $user->organisations()->pluck('organisations.id');

        if ($orgIds->isEmpty()) {
            return [];
        }

        return Event::query()
            ->whereIn('host_organisation_id', $orgIds)
            ->pluck('id')
            ->all();
    }
}
