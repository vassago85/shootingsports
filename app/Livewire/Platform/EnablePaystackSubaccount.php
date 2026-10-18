<?php

namespace App\Livewire\Platform;

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Platform\AgencyAgreement;
use App\Services\Platform\PaystackSubaccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Club-organiser wizard: accept the Collection Agent Agreement and
 * create the Paystack Subaccount that receives split-at-source
 * settlements. Only approved Admins/Secretaries of the organisation
 * may use it.
 */
class EnablePaystackSubaccount extends Component
{
    public Organisation $organisation;

    public bool $agreement_accepted = false;

    public string $settlement_bank = '';

    public string $account_number = '';

    public string $business_name = '';

    public function mount(Organisation $organisation): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $this->canManageBilling($user, $organisation), 403);

        $this->organisation = $organisation;
        $this->business_name = $organisation->name;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'agreement_accepted' => ['accepted'],
            'settlement_bank' => ['required', 'string'],
            'account_number' => ['required', 'string', 'min:6'],
            'business_name' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'agreement_accepted.accepted' => 'You must accept the Collection Agent Agreement before enabling your subaccount.',
        ];
    }

    public function submit(PaystackSubaccountService $service): void
    {
        $this->validate();

        $user = Auth::user();

        abort_unless($user instanceof User && $this->canManageBilling($user, $this->organisation), 403);

        $service->create(
            $this->organisation,
            $user,
            $this->business_name,
            $this->settlement_bank,
            $this->account_number,
            request()->ip(),
        );

        session()->flash('status', 'Paystack subaccount created. Settlements will be verified before payouts begin.');

        $this->dispatch('paystack-subaccount-created');
    }

    public function render(): View
    {
        return view('livewire.platform.enable-paystack-subaccount', [
            'agreementBody' => AgencyAgreement::body(),
            'agreementVersion' => AgencyAgreement::currentVersion(),
        ]);
    }

    private function canManageBilling(User $user, Organisation $organisation): bool
    {
        return $user->organisations()
            ->wherePivot('status', MembershipStatus::Approved->value)
            ->wherePivotIn('role', [OrganisationUserRole::Admin->value, OrganisationUserRole::Secretary->value])
            ->whereKey($organisation->id)
            ->exists();
    }
}
