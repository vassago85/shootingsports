<?php

namespace App\Filament\Resources\AdSlots;

use App\Enums\AdPage;
use App\Enums\PlacementSlot;
use App\Filament\Resources\AdSlots\Pages\CreateAdSlot;
use App\Filament\Resources\AdSlots\Pages\EditAdSlot;
use App\Filament\Resources\AdSlots\Pages\ListAdSlots;
use App\Models\AdSlot;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AdSlotResource extends Resource
{
    protected static ?string $model = AdSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Advertising';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Ad slots';

    protected static ?string $modelLabel = 'ad slot';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('page')
                    ->options(AdPage::class)
                    ->required(),
                Select::make('slot')
                    ->options(PlacementSlot::class)
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->helperText('Enter cents — R 1 500 = 150000')
                    ->numeric()
                    ->required()
                    ->default(0),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('page')
            ->columns([
                TextColumn::make('page')->badge()->sortable(),
                TextColumn::make('slot')->badge()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('price_cents')
                    ->label('Price')
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
            'index' => ListAdSlots::route('/'),
            'create' => CreateAdSlot::route('/create'),
            'edit' => EditAdSlot::route('/{record}/edit'),
        ];
    }
}
