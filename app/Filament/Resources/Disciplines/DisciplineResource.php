<?php

namespace App\Filament\Resources\Disciplines;

use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Enums\OrganisationType;
use App\Filament\Resources\Disciplines\Pages\CreateDiscipline;
use App\Filament\Resources\Disciplines\Pages\EditDiscipline;
use App\Filament\Resources\Disciplines\Pages\ListDisciplines;
use App\Models\Discipline;
use App\Support\YouTube;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class DisciplineResource extends Resource
{
    protected static ?string $model = Discipline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                        if ($livewire instanceof CreateRecord && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->helperText('Auto-generated from the name. Edit only if you need a custom URL.')
                    ->unique(ignoreRecord: true)
                    ->rules(['nullable', 'alpha_dash'])
                    ->dehydrateStateUsing(function (?string $state, Get $get): ?string {
                        if (filled($state)) {
                            return $state;
                        }

                        $name = $get('name');

                        return is_string($name) && $name !== '' ? Str::slug($name) : null;
                    }),
                FileUpload::make('image_path')
                    ->label('Picture')
                    ->disk('media')
                    ->directory('discipline-images')
                    ->visibility('public')
                    ->image()
                    ->maxSize(4096)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Shown on this sport’s page and on the sports directory. JPEG, PNG, or WebP, up to 4 MB.')
                    ->columnSpanFull(),
                Select::make('family')
                    ->options(DisciplineFamily::class)
                    ->required(),
                Select::make('parent_id')
                    ->label('Parent sport')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                CheckboxList::make('division_values')
                    ->label('Divisions')
                    ->options(Division::class)
                    ->dehydrated(false)
                    ->columns(2),
                Repeater::make('videos')
                    ->label('Videos')
                    ->helperText('Shown on the sport page, under the short description.')
                    ->dehydrated(false)
                    ->maxItems(4)
                    ->schema([
                        TextInput::make('title')
                            ->required(),
                        TextInput::make('url')
                            ->label('YouTube URL')
                            ->required()
                            ->rules([
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (! is_string($value) || YouTube::idFromUrl($value) === null) {
                                        $fail('Enter a YouTube link.');
                                    }
                                },
                            ]),
                    ])
                    ->columnSpanFull(),
                Select::make('federation_organisation_id')
                    ->label('South African federation')
                    ->relationship(
                        name: 'federation',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', OrganisationType::Federation),
                    )
                    ->searchable()
                    ->preload()
                    ->helperText('Optional. Shown on the more-information page.'),
                TextInput::make('short_blurb')
                    ->label('Short description')
                    ->required()
                    ->maxLength(320)
                    ->columnSpanFull()
                    ->helperText('Shown on the sport page, with the events and videos.'),
                Textarea::make('body')
                    ->label('More information')
                    ->rows(8)
                    ->columnSpanFull()
                    ->helperText('The longer page: how the sport works, and anything else a new shooter should know.'),
                TextInput::make('typical_distances')
                    ->label('Typical distance')
                    ->helperText('Shown on the more-information page.'),
                Textarea::make('equipment_rules')
                    ->label('Equipment')
                    ->columnSpanFull()
                    ->helperText('Shown on the more-information page.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_published')
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
                ImageColumn::make('image_path')
                    ->label('Picture')
                    ->disk('media'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('family')
                    ->badge()
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->searchable(),
                TextColumn::make('federation_organisation_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('short_blurb')
                    ->searchable(),
                TextColumn::make('typical_distances')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_published')
                    ->boolean(),
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
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisciplines::route('/'),
            'create' => CreateDiscipline::route('/create'),
            'edit' => EditDiscipline::route('/{record}/edit'),
        ];
    }
}
