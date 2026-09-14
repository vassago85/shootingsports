<?php

namespace App\Filament\Desk\Resources\Events\Schemas;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationUserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basics')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->helperText('Auto-generated from the title.')
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        Select::make('host_organisation_id')
                            ->label('Host club / series')
                            ->relationship(
                                name: 'hostOrganisation',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $user = Auth::user();

                                    if ($user?->is_staff) {
                                        return $query->orderBy('name');
                                    }

                                    return $query
                                        ->whereHas(
                                            'users',
                                            fn (Builder $q) => $q->where('users.id', $user?->id)
                                                ->whereIn('organisation_user.role', array_map(
                                                    fn (OrganisationUserRole $role): string => $role->value,
                                                    OrganisationUserRole::canManageEvents(),
                                                )),
                                        )
                                        ->orderBy('name');
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('venue_id')
                            ->label('Venue / range')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload(),
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
                            ->inline(false)
                            ->default(true),
                    ]),

                Section::make('Classification')
                    ->columns(2)
                    ->schema([
                        Select::make('level')
                            ->options(EventLevel::class)
                            ->default(EventLevel::Club->value)
                            ->required(),
                        Select::make('status')
                            ->options([
                                EventStatus::Draft->value => 'Draft (not public)',
                                EventStatus::Planned->value => 'Planned',
                                EventStatus::Confirmed->value => 'Confirmed',
                                EventStatus::EntriesOpen->value => 'Entries open',
                                EventStatus::Full->value => 'Full',
                                EventStatus::Cancelled->value => 'Cancelled',
                                EventStatus::Completed->value => 'Completed',
                            ])
                            ->default(EventStatus::Draft->value)
                            ->required()
                            ->helperText('Draft stays private. Planned/confirmed can appear on the calendar once your listing is published.'),
                    ]),

                Section::make('Fees')
                    ->description('Entered in Rand. Stored as cents.')
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
                            ->label('External entry URL')
                            ->url()
                            ->columnSpanFull()
                            ->helperText('Optional — link to where entries are taken. Leave blank if you do not have one.'),
                        TextInput::make('capacity')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('entries_taken')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Optional manual count.'),
                    ]),

                Section::make('Banner')
                    ->schema([
                        FileUpload::make('banner_path')
                            ->label('Banner image')
                            ->disk('media')
                            ->directory('event-banners')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth(1600)
                            ->imageResizeTargetHeight(900)
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    ]),
            ]);
    }
}
