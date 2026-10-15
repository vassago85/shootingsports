<?php

namespace App\Filament\Desk\Resources\ClubMembers;

use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\ClubMembers\Pages\CreateClubMember;
use App\Filament\Desk\Resources\ClubMembers\Pages\EditClubMember;
use App\Filament\Desk\Resources\ClubMembers\Pages\ListClubMembers;
use App\Filament\Desk\Resources\ClubMembers\RelationManagers\StatusChangesRelationManager;
use App\Filament\Desk\Resources\ClubMembers\Schemas\ClubMemberForm;
use App\Filament\Desk\Resources\ClubMembers\Tables\ClubMembersTable;
use App\Models\ClubMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ClubMemberResource extends Resource
{
    protected static ?string $model = ClubMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'member';

    protected static ?string $pluralModelLabel = 'members';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function form(Schema $schema): Schema
    {
        return ClubMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubMembersTable::configure($table);
    }

    /**
     * Non-staff users see only members in clubs where they hold an
     * Admin or Secretary seat. Staff see every record.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        return $query->whereIn('organisation_id', self::managedClubIds());
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->holdsAnyMemberManagingRole());
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function getRelations(): array
    {
        return [
            StatusChangesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubMembers::route('/'),
            'create' => CreateClubMember::route('/create'),
            'edit' => EditClubMember::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Shared between the resource query, the form club picker and the
     * import / export actions so a single definition decides which
     * clubs the current user may touch.
     *
     * @return list<int>
     */
    public static function managedClubIds(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return $user->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageMembers(),
            ))
            ->pluck('organisation_id')
            ->all();
    }
}
