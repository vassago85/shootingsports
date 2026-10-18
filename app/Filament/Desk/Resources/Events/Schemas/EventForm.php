<?php

namespace App\Filament\Desk\Resources\Events\Schemas;

use App\Enums\EntryCollection;
use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\Province;
use App\Filament\Support\EventDisciplineSelect;
use App\Filament\Support\EventFlagSelect;
use App\Filament\Support\EventPartnerSelect;
use App\Filament\Support\EventVenueRepeater;
use App\Models\Venue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Desk event form. Create uses a 4-step wizard (mockup s4); edit uses
 * the same four groups as tabs. Shared field builders keep both in sync.
 */
class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Event')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Basics')
                            ->schema(self::basicsComponents()),
                        Tab::make('When and where')
                            ->schema(self::whenWhereComponents()),
                        Tab::make('Entry')
                            ->schema(self::entryComponents()),
                        Tab::make('Extras')
                            ->schema(self::extrasComponents(forCreate: false)),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    public static function basicsComponents(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->columnSpanFull()
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if (filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),
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
                ->live()
                ->afterStateUpdated(EventDisciplineSelect::prefillFromHost())
                ->required(),
            EventDisciplineSelect::make(),
            Select::make('level')
                ->options(EventLevel::class)
                ->default(EventLevel::Club->value)
                ->required(),
            Select::make('kind')
                ->label('Type')
                ->options(EventKind::class)
                ->default(EventKind::Competition->value)
                ->required()
                ->helperText('Training is a course or intro day. Competition is a match.'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function whenWhereComponents(): array
    {
        return [
            DateTimePicker::make('starts_at')
                ->label('Start date')
                ->seconds(false)
                ->required(),
            DateTimePicker::make('ends_at')
                ->label('End date')
                ->seconds(false)
                ->helperText('Leave blank for a one-day event.'),
            Toggle::make('all_day')
                ->label('All-day event')
                ->inline(false)
                ->default(true)
                ->helperText('Turn off to set a start time and squad times.')
                ->columnSpanFull(),
            Select::make('venue_id')
                ->label('Range')
                ->relationship('venue', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Range not listed? Use “Create” below. Staff check new ranges before they show publicly.')
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('town')->required()->maxLength(80),
                    Select::make('province')
                        ->options(Province::class)
                        ->required(),
                ])
                ->createOptionUsing(function (array $data): int {
                    $venue = Venue::query()->create([
                        'name' => $data['name'],
                        'town' => $data['town'],
                        'province' => $data['province'],
                        'status' => ListingStatus::Pending,
                        'source' => ListingSource::Submission,
                        'claimed_by' => Auth::id(),
                    ]);

                    return $venue->id;
                })
                ->columnSpanFull(),
            EventVenueRepeater::make(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function entryComponents(): array
    {
        return [
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
            TextInput::make('entry_url')
                ->label('External entry URL')
                ->url()
                ->columnSpanFull()
                ->helperText('Optional link when entries are taken somewhere else.'),
            TextInput::make('contact_email')
                ->label('Organiser email')
                ->email()
                ->columnSpanFull()
                ->helperText('Enquiries about this event are forwarded here. The address is not shown on the public page.'),
            Toggle::make('accepts_platform_entries')
                ->label('Take entries on this site')
                ->inline(false)
                ->columnSpanFull(),
            Select::make('entry_collection')
                ->label('How the fee is paid')
                ->options(EntryCollection::class)
                ->default(EntryCollection::External->value)
                ->required()
                ->helperText('Paystack charges the public entry fee. Leave on “pays the club” until you want to take the money here.')
                ->columnSpanFull(),
            Toggle::make('accepts_team_entries')
                ->label('Take team entries on this site')
                ->helperText('Shows a team entry form on the match page. Entries are emailed to the organiser email.')
                ->live()
                ->inline(false),
            TextInput::make('team_size')
                ->label('Shooters per team')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required(fn (Get $get): bool => (bool) $get('accepts_team_entries')),
            Textarea::make('entry_payment_instructions')
                ->label('Payment instructions for entrants')
                ->rows(4)
                ->maxLength(2000)
                ->columnSpanFull()
                ->helperText('Bank details, reference and closing date. Sent to the team captain with their entry confirmation.'),
            TextInput::make('capacity')
                ->label('Max entries')
                ->numeric()
                ->minValue(0),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function extrasComponents(bool $forCreate = false): array
    {
        $components = [
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
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->columnSpanFull(),
            EventFlagSelect::makeChips(),
            Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),
            EventPartnerSelect::make()->hiddenLabel(),
        ];

        if (! $forCreate) {
            $components[] = Select::make('status')
                ->options([
                    EventStatus::Draft->value => 'Draft (not public)',
                    EventStatus::Planned->value => 'Planned',
                    EventStatus::Confirmed->value => 'Confirmed',
                    EventStatus::EntriesOpen->value => 'Entries open',
                    EventStatus::Full->value => 'Full',
                    EventStatus::Postponed->value => 'Postponed',
                    EventStatus::Cancelled->value => 'Cancelled',
                    EventStatus::Completed->value => 'Completed',
                ])
                ->required()
                ->helperText('You can change status any time. Publication (draft / in review / published) is handled separately.');

            $components[] = TextInput::make('slug')
                ->helperText('Auto-generated from the title. Change only if you need a custom URL.')
                ->unique(ignoreRecord: true)
                ->rules(['alpha_dash'])
                ->columnSpanFull();
        }

        return $components;
    }
}
