<?php

namespace App\Livewire;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Club subscription page. Separate from shooter Pro on /upgrade.
 * Checkout stays closed until CLUB_PLANS_ENABLED is on and the
 * placeholder prices in config/club-plans.php have been filled in.
 */
#[Layout('components.layouts.public', ['title' => 'Club subscription'])]
class ClubPlanCheckout extends Component
{
    public Organisation $organisation;

    public function mount(Organisation $organisation): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless(config('club-plans.enabled'), 404);
        abort_unless($this->canSubscribe($user, $organisation), 403);

        $this->organisation = $organisation;
    }

    public function render(): View
    {
        return view('livewire.club-plan-checkout', [
            'pricing' => config('club-plans.pricing'),
            'ready' => $this->pricesReady(),
        ]);
    }

    public function pricesReady(): bool
    {
        $annual = config('club-plans.pricing.annual.amount_cents');
        $monthly = config('club-plans.pricing.monthly.amount_cents');

        return is_int($annual) && $annual >= 100
            && is_int($monthly) && $monthly >= 100
            && filled(config('services.paystack.club_plan_codes.annual'))
            && filled(config('services.paystack.club_plan_codes.monthly'))
            && filled(config('services.paystack.secret_key'));
    }

    private function canSubscribe(User $user, Organisation $organisation): bool
    {
        return $user->is_staff
            || $user->canManageClubMembers($organisation)
            || $user->canManageOrganisationEvents($organisation)
            || $user->administersOrganisation($organisation);
    }
}
