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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
            ->components([
                Section::make('Basics')
                    ->description('Match title, permalink and the club hosting it.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                                // Only auto-populate the slug on create — never overwrite it
                                // on edit, or bookmarks and cached URLs would break.
                                if ($livewire instanceof CreateRecord && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->helperText('Auto-generated from the title. Edit only if you need a custom URL.')
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        Select::make('host_organisation_id')
                            ->label('Host club / organisation')
                            ->relationship('hostOrganisation', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('venue_id')
                            ->label('Venue / range')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave blank if it will be hosted at the club\'s own range.'),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('When')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Starts')
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Ends')
                            ->seconds(false),
                        Toggle::make('all_day')
                            ->label('All-day match')
                            ->inline(false),
                        DateTimePicker::make('confirmed_at')
                            ->label('Dates confirmed at')
                            ->seconds(false)
                            ->helperText('Auto-set when you use the "Confirm dates" bulk action.'),
                        DateTimePicker::make('original_starts_at')
                            ->label('Original start date')
                            ->seconds(false)
                            ->helperText('Only set if the match was postponed from a previous date.'),
                    ]),

                Section::make('Classification')
                    ->columns(3)
                    ->schema([
                        Select::make('level')
                            ->options(EventLevel::class)
                            ->default('club')
                            ->required(),
                        Select::make('status')
                            ->options(EventStatus::class)
                            ->default('draft')
                            ->required(),
                        Select::make('source')
                            ->options(ListingSource::class)
                            ->default('staff')
                            ->required(),
                    ]),

                Section::make('Fees')
                    ->description('Entered in Rand. Stored internally as cents.')
                    ->columns(2)
                    ->schema([
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
                            ->helperText('Optional discounted fee for club members.')
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null
                                ? number_format($state / 100, 2, '.', '')
                                : null)
                            ->dehydrateStateUsing(fn (mixed $state): ?int => $state !== null && $state !== ''
                                ? (int) round(((float) $state) * 100)
                                : null),
                    ]),

                Section::make('Registration')
                    ->columns(2)
                    ->schema([
                        TextInput::make('entry_url')
                            ->label('External entry / registration URL')
                            ->url()
                            ->columnSpanFull()
                            ->helperText('Optional — link to the external form/site where entries are actually taken (MatchApp, TicketForms, etc). You\'ll need to keep this current manually; leave blank if you don\'t have one.'),
                        TextInput::make('capacity')
                            ->label('Maximum entries')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Optional cap. Leave blank for unlimited.'),
                        TextInput::make('entries_taken')
                            ->label('Entries taken (count)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Optional — only fill in if you\'re manually tracking entries and want the public page to show "X entries taken so far".'),
                    ]),

                Section::make('Match format')
                    ->columns(3)
                    ->schema([
                        TextInput::make('round_count')
                            ->label('Rounds')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('target_count')
                            ->label('Targets')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('stage_count')
                            ->label('Stages')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Section::make('Results')
                    ->schema([
                        TextInput::make('results_url')
                            ->label('Results URL')
                            ->url()
                            ->helperText('Link to results after the match (Practiscore, WinMSS, PDF, etc).'),
                        DateTimePicker::make('last_verified_at')
                            ->label('Details last verified')
                            ->seconds(false),
                    ]),

                Section::make('Banner image')
                    ->description('Uploaded to the media storage volume on the server. JPEG or PNG, up to 5 MB.')
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
                            ->helperText('Landscape works best. Wider than 1200 pixels ideal.'),
                    ]),
            ]);
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
