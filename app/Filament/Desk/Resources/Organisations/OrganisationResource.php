<?php

namespace App\Filament\Desk\Resources\Organisations;

use App\Filament\Desk\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Desk\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Desk\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Desk\Resources\Organisations\Schemas\OrganisationForm;
use App\Filament\Desk\Resources\Organisations\Tables\OrganisationsTable;
use App\Models\Organisation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Your clubs';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'My clubs';
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
        return __('labels.club');
    }

    public static function getPluralModelLabel(): string
    {
        return __('labels.clubs');
    }

    public static function form(Schema $schema): Schema
    {
        return OrganisationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Staff / platform owner see every club. Everyone else is
        // scoped to the clubs they're linked to. claimed_by stays so
        // an owner whose pivot row is missing can still open the
        // listing they own.
        if ($user?->is_staff) {
            return $query;
        }

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->whereHas(
                'users',
                fn (Builder $pivot) => $pivot->where('users.id', $user->id),
            )->orWhere('organisations.claimed_by', $user->id);
        });
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        // Industry-only users (not directors) never see club nav — mockup s5.
        return (bool) ($user?->isDirectorDeskUser() || $user?->is_staff);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'create' => CreateOrganisation::route('/create'),
            'edit' => EditOrganisation::route('/{record}/edit'),
        ];
    }
}
