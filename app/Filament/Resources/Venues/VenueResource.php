<?php

namespace App\Filament\Resources\Venues;

use App\Enums\GautengMetro;
use App\Enums\GeocodeSource;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Filament\Actions\MarkVerifiedBulkAction;
use App\Filament\Actions\MergeVenuesBulkAction;
use App\Filament\Resources\Venues\Pages\CreateVenue;
use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Filament\Resources\Venues\Pages\ListVenues;
use App\Models\Venue;
use App\Support\CoordinatePaste;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('province')
                    ->options(Province::class)
                    ->required(),
                TextInput::make('town')
                    ->required(),
                Select::make('metro')
                    ->options(GautengMetro::class),
                TextInput::make('coordinate_paste')
                    ->label('Paste coordinates')
                    ->helperText('Paste from Google Maps, e.g. -25.952912873030343, 28.486456735843475')
                    ->dehydrated(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $parsed = CoordinatePaste::parse($state);

                        if ($parsed === null) {
                            return;
                        }

                        $set('lat', $parsed['lat']);
                        $set('lng', $parsed['lng']);
                        $set('coordinate_paste', null);
                    }),
                TextInput::make('lat')
                    ->numeric()
                    ->helperText('Saving lat/lng marks the pin as staff-locked so imports never overwrite it.'),
                TextInput::make('lng')
                    ->numeric(),
                Select::make('geocode_source')
                    ->options(GeocodeSource::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Set automatically: nominatim / staff / google.'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('max_distance_m')
                    ->numeric(),
                TextInput::make('bay_count')
                    ->numeric(),
                Select::make('access')
                    ->options(VenueAccess::class)
                    ->default('guest_by_arrangement')
                    ->required(),
                TextInput::make('day_fee_cents')
                    ->numeric(),
                Textarea::make('facilities')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Select::make('tier')
                    ->options(ProviderTier::class)
                    ->default('free')
                    ->required()
                    ->helperText('Paid listing for ranges only — clubs stay free.'),
                Select::make('status')
                    ->options(ListingStatus::class)
                    ->default('pending')
                    ->required(),
                Select::make('verification_state')
                    ->options(VerificationState::class)
                    ->default('unconfirmed')
                    ->required(),
                DateTimePicker::make('last_verified_at'),
                TextInput::make('claimed_by')
                    ->numeric(),
                Select::make('source')
                    ->options(ListingSource::class)
                    ->default('staff')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('province')
                    ->badge()
                    ->searchable(),
                TextColumn::make('town')
                    ->searchable(),
                TextColumn::make('metro')
                    ->badge()
                    ->searchable(),
                TextColumn::make('lat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lng')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_distance_m')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bay_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('access')
                    ->badge()
                    ->searchable(),
                TextColumn::make('day_fee_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tier')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('verification_state')
                    ->badge()
                    ->searchable(),
                TextColumn::make('last_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('claimed_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source')
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
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('verification_state')->options(VerificationState::class),
                SelectFilter::make('province')->options(Province::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MergeVenuesBulkAction::make(),
                    MarkVerifiedBulkAction::make(),
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
            'index' => ListVenues::route('/'),
            'create' => CreateVenue::route('/create'),
            'edit' => EditVenue::route('/{record}/edit'),
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
