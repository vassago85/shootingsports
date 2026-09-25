<?php

namespace App\Livewire\Suppliers;

use App\Enums\ClaimStatus;
use App\Enums\ListingStatus;
use App\Mail\SupplierClaimSubmittedMail;
use App\Models\Claim;
use App\Models\Provider;
use App\Support\StaffInbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * A signed-in person asks to take over a staff-listed business.
 * Staff approve it from the claims queue, which sets claimed_by.
 */
class ClaimListing extends Component
{
    public Provider $provider;

    #[Validate('required|string|min:20|max:2000')]
    public string $evidence = '';

    /**
     * Why the form is hidden: taken, already-listed, or pending.
     */
    public ?string $blocked = null;

    public function mount(Provider $provider): void
    {
        abort_unless($provider->status === ListingStatus::Published, 404);
        abort_unless($provider->category === null || $provider->category->isPublic(), 404);

        $this->provider = $provider;

        $user = Auth::user();

        if ($provider->claimed_by === $user?->id) {
            $this->redirect(route('suppliers.onboard.thanks', ['provider' => $provider->slug]), navigate: false);

            return;
        }

        if ($provider->claimed_by !== null) {
            $this->blocked = 'taken';

            return;
        }

        if ($user !== null && $user->providers()->exists()) {
            $this->blocked = 'already-listed';

            return;
        }

        if ($user !== null && $this->pendingClaim($user->id, $provider)->exists()) {
            $this->blocked = 'pending';
        }
    }

    public function submit(): void
    {
        $user = Auth::user();

        if ($user === null || $this->blocked !== null) {
            return;
        }

        if ($this->provider->claimed_by !== null) {
            $this->blocked = 'taken';

            return;
        }

        if ($user->providers()->exists()) {
            $this->blocked = 'already-listed';

            return;
        }

        if ($this->pendingClaim($user->id, $this->provider)->exists()) {
            $this->blocked = 'pending';

            return;
        }

        $this->validate();

        $claim = Claim::query()->create([
            'claimable_type' => 'provider',
            'claimable_id' => $this->provider->id,
            'user_id' => $user->id,
            'evidence' => trim($this->evidence),
            'status' => ClaimStatus::Pending,
        ]);

        StaffInbox::queue(new SupplierClaimSubmittedMail($claim, $this->provider), $user->email);

        $this->blocked = 'pending';
        $this->evidence = '';
    }

    public function render()
    {
        return view('livewire.suppliers.claim-listing')
            ->layout('components.layouts.public', ['title' => 'Claim '.$this->provider->name]);
    }

    private function pendingClaim(int $userId, Provider $provider): Builder
    {
        return Claim::query()
            ->where('user_id', $userId)
            ->where('claimable_type', 'provider')
            ->where('claimable_id', $provider->id)
            ->where('status', ClaimStatus::Pending);
    }
}
