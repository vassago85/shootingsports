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

    protected static string|UnitEnum|null $navigationGroup = 'Your listings';

    protected static ?string $navigationLabel = 'Clubs & series';

    protected static ?string $modelLabel = 'listing';

    protected static ?string $pluralModelLabel = 'listings';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

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

        if ($user?->is_staff) {
            return $query;
        }

        return $query->whereHas(
            'users',
            fn (Builder $q) => $q->where('users.id', $user?->id),
        );
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
