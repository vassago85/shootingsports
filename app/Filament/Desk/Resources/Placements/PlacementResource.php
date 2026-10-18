<?php

namespace App\Filament\Desk\Resources\Placements;

use App\Filament\Desk\Resources\Placements\Pages\ManagePlacements;
use App\Models\Placement;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Industry desk — read-only view of the signed-in business's ad
 * placements (impressions and clicks). Staff place bookings in admin;
 * suppliers only see their own stats (mockup s5).
 */
class PlacementResource extends Resource
{
    protected static ?string $model = Placement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Your business';

    protected static ?string $navigationLabel = 'Adverts';

    protected static ?string $modelLabel = 'advert';

    protected static ?string $pluralModelLabel = 'adverts';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('headline')
                    ->label('Advert')
                    ->placeholder(fn (Placement $record): string => $record->slot->getLabel())
                    ->searchable(),
                TextColumn::make('slot')
                    ->badge(),
                TextColumn::make('starts_on')
                    ->label('From')
                    ->date('j M Y'),
                TextColumn::make('ends_on')
                    ->label('Until')
                    ->date('j M Y'),
                TextColumn::make('impressions')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clicks')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
            ])
            ->defaultSort('starts_on', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        return $query->whereIn('provider_id', self::managedProviderIds());
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->is_staff) {
            return Placement::query()->exists();
        }

        if (! $user->isIndustryDeskUser()) {
            return false;
        }

        return Placement::query()
            ->whereIn('provider_id', self::managedProviderIds())
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
            'index' => ManagePlacements::route('/'),
        ];
    }

    /**
     * @return list<int>
     */
    public static function managedProviderIds(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        return $user->providers()->pluck('id')
            ->merge($user->managedProviders()->pluck('providers.id'))
            ->unique()
            ->values()
            ->all();
    }
}
