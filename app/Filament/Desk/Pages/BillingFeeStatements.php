<?php

namespace App\Filament\Desk\Pages;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\PlatformFeeStatement;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Archive of the monthly platform fee statements for the clubs the
 * current Desk user manages, each downloadable as CSV.
 */
class BillingFeeStatements extends Page
{
    protected string $view = 'filament.desk.pages.billing-fee-statements';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Fee statements';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'billing-fee-statements';

    protected static ?string $title = 'Fee statements';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->is_staff || $user->holdsAnyMemberManagingRole());
    }

    /**
     * @return Collection<int, PlatformFeeStatement>
     */
    public function statements(): Collection
    {
        return PlatformFeeStatement::query()
            ->whereIn('organisation_id', ClubMemberResource::managedClubIds())
            ->with('organisation')
            ->orderByDesc('period_start')
            ->get();
    }
}
