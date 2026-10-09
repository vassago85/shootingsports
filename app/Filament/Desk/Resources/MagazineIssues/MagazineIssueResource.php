<?php

namespace App\Filament\Desk\Resources\MagazineIssues;

use App\Filament\Desk\Resources\MagazineIssues\Pages\CreateMagazineIssue;
use App\Filament\Desk\Resources\MagazineIssues\Pages\EditMagazineIssue;
use App\Filament\Desk\Resources\MagazineIssues\Pages\ListMagazineIssues;
use App\Filament\Desk\Resources\MagazineIssues\Schemas\MagazineIssueForm;
use App\Filament\Desk\Resources\MagazineIssues\Tables\MagazineIssuesTable;
use App\Models\MagazineIssue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MagazineIssueResource extends Resource
{
    protected static ?string $model = MagazineIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Stories';

    protected static ?string $navigationLabel = 'Magazine issues';

    protected static ?string $modelLabel = 'magazine issue';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->is_media_partner);
    }

    public static function form(Schema $schema): Schema
    {
        return MagazineIssueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MagazineIssuesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        return $query->where('user_id', $user?->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMagazineIssues::route('/'),
            'create' => CreateMagazineIssue::route('/create'),
            'edit' => EditMagazineIssue::route('/{record}/edit'),
        ];
    }
}
