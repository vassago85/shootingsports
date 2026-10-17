<?php

namespace App\Filament\Resources\Providers;

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Filament\Actions\MarkVerifiedBulkAction;
use App\Filament\Resources\Providers\Pages\CreateProvider;
use App\Filament\Resources\Providers\Pages\EditProvider;
use App\Filament\Resources\Providers\Pages\ListProviders;
use App\Models\Provider;
use App\Support\ProviderTierPricing;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('labels.industry_nav');
    }

    public static function getModelLabel(): string
    {
        return __('labels.industry_listing');
    }

    public static function getPluralModelLabel(): string
    {
        return __('labels.industry_listings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('category')
                    ->options(ProviderCategory::class)
                    ->required(),
                CheckboxList::make('services')
                    ->label('Additional services')
                    ->helperText('Other categories this supplier offers beyond the primary one.')
                    ->options(collect(ProviderCategory::cases())
                        ->mapWithKeys(fn (ProviderCategory $c): array => [$c->value => $c->getLabel()])
                        ->all())
                    ->columns(2)
                    ->columnSpanFull(),
                Select::make('province')
                    ->options(Province::class)
                    ->searchable()
                    ->required(),
                TextInput::make('town')
                    ->required(),
                Select::make('metro')
                    ->options(GautengMetro::class),
                TextInput::make('lat')
                    ->numeric(),
                TextInput::make('lng')
                    ->numeric(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('website_url')
                    ->url(),
                TextInput::make('tagline')
                    ->label('Short description')
                    ->maxLength(160)
                    ->helperText('One line on directory cards and at the top of the public page.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('About')
                    ->helperText('Longer profile copy under Services.')
                    ->columnSpanFull(),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->disk('media')
                    ->directory('provider-logos')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(512)
                    ->imageResizeTargetHeight(512)
                    ->maxSize(3072)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->visible(function (Get $get): bool {
                        $tier = $get('tier');

                        return $tier === ProviderTier::Featured || $tier === ProviderTier::Featured->value;
                    })
                    ->helperText('Pictures are part of an enhanced listing. This logo is shown on the public directory only while the tier is Enhanced.')
                    ->columnSpanFull(),
                Select::make('tier')
                    ->options(ProviderTier::class)
                    ->default('free')
                    ->required()
                    ->live()
                    ->helperText(fn ($state): ?string => ProviderTierPricing::helperFor($state)),
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
            ->defaultPaginationPageOption(25)
            ->columns([
                // Default-visible shortlist per the Phase 1 brief:
                // name, category, town, province, status, verification.
                // Everything else is toggleable and hidden by default.
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('media')
                    ->imageSize(40)
                    ->square()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->searchable(),
                TextColumn::make('town')
                    ->searchable(),
                TextColumn::make('province')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('verification_state')
                    ->label('Verification')
                    ->badge()
                    ->searchable(),
                TextColumn::make('tier')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('metro')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website_url')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lat')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lng')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('claimed_by')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Filter::make('on_public_register')
                    ->label('On the public register')
                    ->query(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false)
                        ? $query->published()->listed()
                        : $query),
                SelectFilter::make('status')->options(ListingStatus::class),
                SelectFilter::make('verification_state')->options(VerificationState::class),
                SelectFilter::make('category')->options(ProviderCategory::class),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'edit' => EditProvider::route('/{record}/edit'),
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
