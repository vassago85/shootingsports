<?php

namespace App\Filament\Resources\Organisations;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Filament\Actions\MarkVerifiedBulkAction;
use App\Filament\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Resources\Organisations\RelationManagers\MembershipsRelationManager;
use App\Models\Organisation;
use BackedEnum;
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
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?string $navigationLabel = 'Clubs & series';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                                if ($livewire instanceof CreateRecord && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->helperText('Auto-generated from the name. Edit only if you need a custom URL.')
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        TextInput::make('short_name'),
                        Select::make('type')
                            ->options(OrganisationType::class)
                            ->required()
                            ->live()
                            // Non-blocking guard rail. If someone types
                            // "Dwarskloof Shooting Range" and picks
                            // Club, we point them at the Ranges resource
                            // rather than silently create a ghost that
                            // pollutes the /clubs directory (this was
                            // the whole reason for the 2026_09_15
                            // reclassification migration).
                            ->helperText(function ($get, $state): ?string {
                                $name = (string) $get('name');
                                $isClub = $state === 'club' || $state === OrganisationType::Club->value;

                                if (! $isClub || $name === '') {
                                    return null;
                                }

                                if (preg_match('/\b(range|shooting range|skietbaan)\b/i', $name) !== 1) {
                                    return null;
                                }

                                return '⚠ "'.$name.'" reads like a range. If it is a venue, not an external club, add it under Ranges instead so it does not appear on /clubs.';
                            }),
                        Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('province')
                            ->options(Province::class),
                        TextInput::make('town'),
                        Select::make('status')
                            ->options(ListingStatus::class)
                            ->default(ListingStatus::Pending->value)
                            ->required(),
                        Select::make('verification_state')
                            ->options(VerificationState::class)
                            ->default(VerificationState::Unconfirmed->value)
                            ->required(),
                        Toggle::make('accredited')
                            ->inline(false)
                            ->default(false),
                        Toggle::make('visitors_welcome')
                            ->inline(false)
                            ->default(false),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')->email(),
                        TextInput::make('phone')->tel(),
                        TextInput::make('website_url')->url()->columnSpanFull(),
                        TextInput::make('facebook_url')->url()->columnSpanFull(),
                    ]),

                Section::make('Logo')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('media')
                            ->directory('organisation-logos')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth(512)
                            ->imageResizeTargetHeight(512)
                            ->maxSize(3072)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    ]),

                Section::make('Staff meta')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('source')
                            ->options(ListingSource::class)
                            ->default(ListingSource::Staff->value)
                            ->required(),
                        DateTimePicker::make('last_verified_at')->seconds(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('media')
                    ->circular()
                    ->imageSize(40)
                    ->defaultImageUrl(fn (?Organisation $record): string => $record?->initialsAvatarDataUri()
                        ?? (new Organisation(['name' => 'Shooting Sports']))->initialsAvatarDataUri()),
                TextColumn::make('name')->searchable()->sortable()->wrap(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn (OrganisationType|string|null $state): string => match ($state instanceof OrganisationType ? $state : OrganisationType::tryFrom((string) $state)) {
                        OrganisationType::Club => 'info',
                        OrganisationType::Series => 'warning',
                        OrganisationType::Federation => 'primary',
                        OrganisationType::Association => 'teal',
                        OrganisationType::ProvincialBody => 'cyan',
                        default => 'gray',
                    }),
                TextColumn::make('town')
                    ->label('Location')
                    ->getStateUsing(fn (Organisation $record): string => $record->locationLabel())
                    ->searchable(['town', 'province'])
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (ListingStatus|string|null $state): string => match ($state instanceof ListingStatus ? $state : ListingStatus::tryFrom((string) $state)) {
                        ListingStatus::Published => 'success',
                        ListingStatus::Pending => 'warning',
                        ListingStatus::Archived => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('verification_state')
                    ->badge()
                    ->toggleable()
                    ->color(fn (VerificationState|string|null $state): string => match ($state instanceof VerificationState ? $state : VerificationState::tryFrom((string) $state)) {
                        VerificationState::Verified => 'success',
                        VerificationState::Ageing => 'warning',
                        VerificationState::Unconfirmed => 'gray',
                        VerificationState::Archived => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('accredited')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('verification_state')->options(VerificationState::class),
                SelectFilter::make('status')->options(ListingStatus::class),
                SelectFilter::make('type')->options(OrganisationType::class),
                SelectFilter::make('province')->options(Province::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
            MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'create' => CreateOrganisation::route('/create'),
            'edit' => EditOrganisation::route('/{record}/edit'),
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
