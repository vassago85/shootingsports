<?php

namespace App\Filament\Desk\Resources\MembershipInvoices;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Filament\Desk\Resources\MembershipInvoices\Pages\CreateMembershipInvoice;
use App\Filament\Desk\Resources\MembershipInvoices\Pages\EditMembershipInvoice;
use App\Filament\Desk\Resources\MembershipInvoices\Pages\ListMembershipInvoices;
use App\Filament\Desk\Resources\MembershipInvoices\Schemas\MembershipInvoiceForm;
use App\Filament\Desk\Resources\MembershipInvoices\Tables\MembershipInvoicesTable;
use App\Models\MembershipInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MembershipInvoiceResource extends Resource
{
    protected static ?string $model = MembershipInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $modelLabel = 'invoice';

    protected static ?string $pluralModelLabel = 'invoices';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return MembershipInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipInvoicesTable::configure($table);
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
            'index' => ListMembershipInvoices::route('/'),
            'create' => CreateMembershipInvoice::route('/create'),
            'edit' => EditMembershipInvoice::route('/{record}/edit'),
        ];
    }
}
