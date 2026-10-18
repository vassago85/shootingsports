<?php

namespace App\Filament\Desk\Pages;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Club-manager view of the platform subscription: tier, member usage,
 * lifecycle dates and the next billing amount, for the clubs the
 * current Desk user manages.
 */
class BillingDashboard extends Page
{
    protected string $view = 'filament.desk.pages.billing-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'billing';

    protected static ?string $title = 'Billing dashboard';

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

    /**
     * @return Collection<int, PlatformSubscription>
     */
    public function subscriptions(): Collection
    {
        return PlatformSubscription::query()
            ->whereIn('organisation_id', ClubMemberResource::managedClubIds())
            ->with('organisation')
            ->get();
    }

    public function changeTier(int $organisationId): void
    {
        abort_unless(in_array($organisationId, ClubMemberResource::managedClubIds(), true), 403);

        Notification::make()
            ->title('Changing tier is coming soon')
            ->body('Contact the platform operator to change your tier for now.')
            ->info()
            ->send();
    }

    public function cancelSubscription(int $organisationId): void
    {
        abort_unless(in_array($organisationId, ClubMemberResource::managedClubIds(), true), 403);

        Notification::make()
            ->title('Cancelling online is coming soon')
            ->body('Contact the platform operator to cancel your subscription for now.')
            ->info()
            ->send();
    }

    public function requestSubscription(int $organisationId): void
    {
        abort_unless(in_array($organisationId, ClubMemberResource::managedClubIds(), true), 403);

        Notification::make()
            ->title('No subscription yet')
            ->body('Contact the platform operator to set up a subscription for this club.')
            ->info()
            ->send();
    }
}
