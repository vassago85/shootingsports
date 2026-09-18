<?php

namespace App\Filament\Resources\Events;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Support\EventDisciplineSelect;
use App\Filament\Support\EventFlagSelect;
use App\Filament\Support\EventVenueRepeater;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
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
            ->columns(1)
            ->components([
                Tabs::make('Event')
                    ->persistTab()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Details')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('title')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                                            if ($livewire instanceof CreateRecord && filled($state)) {
                                                $set('slug', Str::slug($state));
                                            }
                                        }),
                                    TextInput::make('slug')
                                        ->helperText('Auto-generated from the title.')
                                        ->unique(ignoreRecord: true)
                                        ->rules(['alpha_dash']),
                                ]),
                                Grid::make(3)->schema([
                                    Select::make('host_organisation_id')
                                        ->label('Host club / organisation')
                                        ->relationship('hostOrganisation', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(EventDisciplineSelect::prefillFromHost())
                                        // Optional: some matches are hosted by the range
                                        // itself (private range fundraiser, farm shoot).
                                        // Desk form keeps this required — a director
                                        // acts as their own org — but staff creating
                                        // in admin may leave it blank when the venue
                                        // is the "host".
                                        ->columnSpan(2)
                                        ->hintIcon(Heroicon::OutlinedInformationCircle, 'Leave blank when the range operator hosts (no external club).'),
                                    Select::make('venue_id')
                                        ->label('Venue / range')
                                        ->relationship('venue', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->hintIcon(Heroicon::OutlinedInformationCircle, 'Primary venue. Use "Additional venues" below for multi-day/multi-range matches.'),
                                ]),
                                EventDisciplineSelect::make()
                                    ->helperText('First selected is the primary on cards.'),
                                EventVenueRepeater::make(),
                                Textarea::make('description')
                                    ->rows(8)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Schedule')
                            ->schema([
                                Grid::make(2)->schema([
                                    self::dateTimePicker('starts_at')
                                        ->label('Starts')
                                        ->required(),
                                    self::dateTimePicker('ends_at')
                                        ->label('Ends')
                                        ->helperText('Set this for a two-day or longer match. Leave blank for a single day.'),
                                ]),
                                Toggle::make('all_day')
                                    ->label('All-day match')
                                    ->inline(false),
                                Grid::make(2)->schema([
                                    self::dateTimePicker('confirmed_at')
                                        ->label('Dates confirmed at')
                                        ->helperText('Set by the Confirm dates bulk action.'),
                                    self::dateTimePicker('original_starts_at')
                                        ->label('Original start date')
                                        ->helperText('Only if the match was postponed.'),
                                ]),
                            ]),

                        Tab::make('Classification & Format')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('level')
                                        ->options(EventLevel::class)
                                        ->native(false)
                                        ->default(EventLevel::Club)
                                        ->required(),
                                    Select::make('status')
                                        ->options(EventStatus::class)
                                        ->native(false)
                                        ->default(EventStatus::Draft)
                                        ->required(),
                                    Select::make('source')
                                        ->options(ListingSource::class)
                                        ->native(false)
                                        ->default(ListingSource::Staff)
                                        ->required(),
                                ]),
                                Grid::make(4)->schema([
                                    TextInput::make('round_count')
                                        ->label('Rounds')
                                        ->numeric()
                                        ->minValue(0)
                                        ->columnSpan(1),
                                    TextInput::make('target_count')
                                        ->label('Targets')
                                        ->numeric()
                                        ->minValue(0)
                                        ->columnSpan(1),
                                    TextInput::make('stage_count')
                                        ->label('Stages')
                                        ->numeric()
                                        ->minValue(0)
                                        ->columnSpan(1),
                                ]),
                                EventFlagSelect::make(),
                            ]),

                        Tab::make('Entry & Fees')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('entry_fee_cents')
                                        ->label('Public entry fee')
                                        ->prefix('R')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step('0.01')
                                        ->formatStateUsing(fn (?int $state): ?string => $state !== null
                                            ? number_format($state / 100, 2, '.', '')
                                            : null)
                                        ->dehydrateStateUsing(fn (mixed $state): ?int => $state !== null && $state !== ''
                                            ? (int) round(((float) $state) * 100)
                                            : null),
                                    TextInput::make('member_fee_cents')
                                        ->label('Member entry fee')
                                        ->prefix('R')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step('0.01')
                                        ->helperText('Optional club-member rate.')
                                        ->formatStateUsing(fn (?int $state): ?string => $state !== null
                                            ? number_format($state / 100, 2, '.', '')
                                            : null)
                                        ->dehydrateStateUsing(fn (mixed $state): ?int => $state !== null && $state !== ''
                                            ? (int) round(((float) $state) * 100)
                                            : null),
                                ]),
                                TextInput::make('entry_url')
                                    ->label('External entry / registration URL')
                                    ->url()
                                    ->hintIcon(
                                        Heroicon::OutlinedInformationCircle,
                                        'Link to MatchApp, TicketForms, or another entry site. Keep it current, or leave blank.',
                                    ),
                                Grid::make(2)->schema([
                                    TextInput::make('capacity')
                                        ->label('Maximum entries')
                                        ->numeric()
                                        ->minValue(0)
                                        ->helperText('Leave blank for unlimited.'),
                                    TextInput::make('entries_taken')
                                        ->label('Entries taken')
                                        ->numeric()
                                        ->minValue(0)
                                        ->helperText('Manual count for the public page.'),
                                ]),
                            ]),

                        Tab::make('Media & Results')
                            ->schema([
                                FileUpload::make('banner_path')
                                    ->label('Banner')
                                    ->disk('media')
                                    ->directory('event-banners')
                                    ->visibility('public')
                                    ->image()
                                    ->imageEditor()
                                    ->imageResizeMode('cover')
                                    ->imageResizeTargetWidth(1600)
                                    ->imageResizeTargetHeight(900)
                                    ->maxSize(5120)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->hintIcon(Heroicon::OutlinedInformationCircle, 'JPEG or PNG, up to 5 MB. Landscape, wider than 1200px.'),
                                Grid::make(2)->schema([
                                    TextInput::make('results_url')
                                        ->label('Results URL')
                                        ->url()
                                        ->helperText('Practiscore, WinMSS, or a PDF.'),
                                    self::dateTimePicker('last_verified_at')
                                        ->label('Details last verified'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    private static function dateTimePicker(string $name): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->native(false)
            ->seconds(false)
            ->displayFormat('D j M Y, H:i');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('hostOrganisation.name')
                    ->label('Host')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('disciplines.name')
                    ->label('Disciplines')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('D j M Y, H:i')
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('entry_fee_cents')
                    ->label('Entry fee')
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? 'R'.number_format($state / 100, 2)
                        : '—')
                    ->sortable(),
                TextColumn::make('entries_taken')
                    ->label('Entries')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_verified_at')
                    ->dateTime('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
