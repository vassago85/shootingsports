<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits;

use App\Filament\Desk\Resources\ClubGuestVisits\Pages\CreateClubGuestVisit;
use App\Filament\Desk\Resources\ClubGuestVisits\Pages\EditClubGuestVisit;
use App\Filament\Desk\Resources\ClubGuestVisits\Pages\ListClubGuestVisits;
use App\Filament\Desk\Resources\ClubGuestVisits\Schemas\ClubGuestVisitForm;
use App\Filament\Desk\Resources\ClubGuestVisits\Tables\ClubGuestVisitsTable;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubGuestVisit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ClubGuestVisitResource extends Resource
{
    protected static ?string $model = ClubGuestVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?string $navigationLabel = 'Guest days';

    protected static ?string $modelLabel = 'guest visit';

    protected static ?string $pluralModelLabel = 'guest visits';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClubGuestVisitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubGuestVisitsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        return $query->whereIn('organisation_id', ClubMemberResource::managedClubIds());
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->holdsClubMemberManagingRole());
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubGuestVisits::route('/'),
            'create' => CreateClubGuestVisit::route('/create'),
            'edit' => EditClubGuestVisit::route('/{record}/edit'),
        ];
    }
}
