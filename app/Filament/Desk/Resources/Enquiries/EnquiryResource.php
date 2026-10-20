<?php

namespace App\Filament\Desk\Resources\Enquiries;

use App\Enums\EnquiryStatus;
use App\Filament\Desk\Resources\Enquiries\Pages\ManageEnquiries;
use App\Filament\Desk\Resources\Placements\PlacementResource;
use App\Models\Enquiry;
use App\Models\Provider;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Industry desk — enquiries about the signed-in business's listing.
 * Read-only; staff see every enquiry here (same list as admin Inbox)
 * so a staff user supporting an industry account doesn't have to
 * flip between panels.
 */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Your business';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enquiry')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('subject')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('body')->columnSpanFull()->markdown(),
                        TextEntry::make('created_at')->dateTime('j M Y H:i')->label('Received'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('subject')
                    ->limit(40)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('j M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Staff see every enquiry regardless of type — the industry
        // about_type / about_id scope is only applied for non-staff
        // industry users looking at their own listing's leads.
        if ($user?->is_staff) {
            return $query;
        }

        $providerIds = PlacementResource::managedProviderIds();

        return $query
            ->where('about_type', Provider::class)
            ->whereIn('about_id', $providerIds !== [] ? $providerIds : [0]);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->is_staff || $user->isIndustryDeskUser());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', EnquiryStatus::New->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnquiries::route('/'),
        ];
    }
}
