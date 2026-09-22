<?php

namespace App\Filament\Resources\Placements;

use App\Enums\Division;
use App\Enums\PlacementSlot;
use App\Filament\Resources\Placements\Pages\CreatePlacement;
use App\Filament\Resources\Placements\Pages\EditPlacement;
use App\Filament\Resources\Placements\Pages\ListPlacements;
use App\Models\Placement;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlacementResource extends Resource
{
    protected static ?string $model = Placement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Advertising';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Placements';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking')
                    ->columns(2)
                    ->schema([
                        Select::make('provider_id')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('ad_slot_id')
                            ->label('Ad slot')
                            ->relationship(
                                name: 'adSlot',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('page'),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Catalog entry sets page + list price. Slot below must match.'),
                        Select::make('slot')
                            ->options(PlacementSlot::class)
                            ->required(),
                        Select::make('division')
                            ->options(Division::class)
                            ->placeholder('Not a division advert')
                            ->helperText('With no sports selected, this is the advert for the whole division. A Handgun advert stays off Shotgun.'),
                        Select::make('disciplines')
                            ->relationship('disciplines', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Sports for this advert. One booking can cover several: Precision Rifle, PRS, PR22, and NRL Hunter share a PRS advert; Gong Shooting is its own; F-Class and Benchrest share one. It shows on those sports and their matches, under the division advert.')
                            ->columnSpanFull(),
                        DatePicker::make('starts_on')->required(),
                        DatePicker::make('ends_on')->required(),
                        TextInput::make('rate_cents')
                            ->label('Invoiced rate (cents)')
                            ->numeric()
                            ->required()
                            ->default(0),
                        Toggle::make('is_active')->default(true)->required(),
                    ]),
                Section::make('Creative')
                    ->columns(2)
                    ->schema([
                        TextInput::make('headline')->maxLength(120)->columnSpanFull(),
                        Textarea::make('body')->rows(3)->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Image')
                            ->disk('media')
                            ->directory('placements')
                            ->image()
                            ->maxSize(2048),
                        TextInput::make('click_url')
                            ->label('Click URL')
                            ->url()
                            ->maxLength(500),
                    ]),
                Section::make('Counters')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('impressions')->numeric()->default(0)->required(),
                        TextInput::make('clicks')->numeric()->default(0)->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('provider.name')->searchable()->sortable(),
                TextColumn::make('adSlot.name')->label('Slot catalog')->toggleable(),
                TextColumn::make('slot')->badge(),
                TextColumn::make('headline')->limit(30)->toggleable(),
                TextColumn::make('starts_on')->date()->sortable(),
                TextColumn::make('ends_on')->date()->sortable(),
                TextColumn::make('rate_cents')
                    ->label('Rate')
                    ->formatStateUsing(fn (?int $state): string => 'R '.number_format(($state ?? 0) / 100, 0, '.', ' '))
                    ->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlacements::route('/'),
            'create' => CreatePlacement::route('/create'),
            'edit' => EditPlacement::route('/{record}/edit'),
        ];
    }
}
