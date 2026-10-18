<?php

namespace App\Filament\Desk\Pages;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\Organisation;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Thin wrapper that hosts the EnablePaystackSubaccount wizard for the
 * chosen club (`?organisation=` query string, defaulting to the first
 * managed club).
 */
class BillingPaystack extends Page
{
    protected string $view = 'filament.desk.pages.billing-paystack';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Paystack';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'billing-paystack';

    protected static ?string $title = 'Paystack payouts';

    public ?int $organisationId = null;

    public function mount(): void
    {
        $requested = request()->integer('organisation');
        $managedIds = ClubMemberResource::managedClubIds();

        $this->organisationId = in_array($requested, $managedIds, true)
            ? $requested
            : ($managedIds[0] ?? null);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->is_staff || $user->holdsAnyMemberManagingRole());
    }

    /**
     * @return Collection<int, Organisation>
     */
    public function managedClubs(): Collection
    {
        return Organisation::query()
            ->whereIn('id', ClubMemberResource::managedClubIds())
            ->orderBy('name')
            ->get();
    }

    public function selectedOrganisation(): ?Organisation
    {
        if ($this->organisationId === null
            || ! in_array($this->organisationId, ClubMemberResource::managedClubIds(), true)) {
            return null;
        }

        return Organisation::query()->find($this->organisationId);
    }
}
