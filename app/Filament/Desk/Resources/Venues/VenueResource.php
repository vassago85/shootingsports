<?php

namespace App\Filament\Desk\Resources\Venues;

use App\Enums\MembershipStatus;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use App\Filament\Desk\Resources\Providers;
use App\Filament\Desk\Resources\Venues\Pages\EditVenue;
use App\Filament\Desk\Resources\Venues\Pages\ListVenues;
use App\Filament\Desk\Resources\Venues\Schemas\VenueForm;
use App\Filament\Desk\Resources\Venues\Tables\VenuesTable;
use App\Models\Venue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Range owner surface inside /desk. Mirrors {@see OrganisationResource}
 * for clubs and {@see Providers}-equivalent
 * shops: scope by the venue_user pivot (approved only), hide the
 * nav for anyone who is not a range manager, and leave Create to
 * staff + the public claim flow — range owners get here by claiming
 * an existing listing, not by inventing one from the desk.
 */
class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Your ranges';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'My ranges';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getModelLabel(): string
    {
        return 'range';
    }

    public static function getPluralModelLabel(): string
    {
        return 'ranges';
    }

    public static function form(Schema $schema): Schema
    {
        return VenueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenuesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Staff see every range for support and moderation; everyone
        // else is scoped to approved venue_user memberships only.
        // The legacy `claimed_by` fallback is included so range
        // owners whose rows pre-date the pivot still see their
        // listing until the shim is dropped.
        if ($user?->is_staff) {
            return $query;
        }

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->whereHas(
                'users',
                fn (Builder $pivot) => $pivot
                    ->where('users.id', $user->id)
                    ->where('venue_user.status', MembershipStatus::Approved->value),
            )->orWhere('venues.claimed_by', $user->id);
        });
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->isRangeManager());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'edit' => EditVenue::route('/{record}/edit'),
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
