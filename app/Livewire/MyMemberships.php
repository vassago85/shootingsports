<?php

namespace App\Livewire;

use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shooter-facing list of the memberships linked to the signed-in
 * user, with per-row CTA to the detail/renew page. A member is
 * "mine" when `ClubMember.user_id` matches the signed-in user, or
 * when the ClubMember's lowercase email matches the user's email —
 * secretaries can import members in bulk before accounts exist, and
 * the Verified listener links them later.
 */
#[Layout('components.layouts.public', ['title' => 'My memberships'])]
class MyMemberships extends Component
{
    public function render(): View
    {
        /** @var User|null $user */
        $user = auth()->user();

        return view('livewire.my-memberships', [
            'memberships' => $user === null
                ? Collection::make()
                : $this->memberships($user),
        ]);
    }

    /** @return Collection<int, ClubMember> */
    private function memberships(User $user): Collection
    {
        return ClubMember::query()
            ->with(['organisation', 'invoices' => fn ($q) => $q->orderBy('period_start', 'desc')])
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhere('email', mb_strtolower((string) $user->email));
            })
            ->orderBy('expires_at')
            ->get();
    }
}
