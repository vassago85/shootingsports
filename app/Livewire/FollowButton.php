<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Discipline;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Follow toggle on club / range / discipline show pages. On over-limit
 * the observer throws PlanLimitExceeded; we catch it here and dispatch
 * an upgrade-prompt event with the trigger key so the modal can open.
 *
 * The morph type comes from the URL, not user input — always one of
 * the mapped values in AppServiceProvider's morphMap.
 */
class FollowButton extends Component
{
    public string $followableType;

    public int $followableId;

    public bool $following = false;

    public string $label = 'Follow';

    public function mount(string $type, int $id, ?string $label = null): void
    {
        $this->followableType = $type;
        $this->followableId = $id;
        $this->label = $label ?? 'Follow';

        $this->following = $this->currentFollow()?->exists ?? false;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(url('/desk/login'));

            return;
        }

        $model = $this->resolve();

        if ($model === null) {
            return;
        }

        $existing = Follow::query()
            ->where('user_id', $user->id)
            ->where('followable_type', $this->followableType)
            ->where('followable_id', $this->followableId)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->following = false;

            return;
        }

        try {
            Follow::create([
                'user_id' => $user->id,
                'followable_type' => $this->followableType,
                'followable_id' => $this->followableId,
            ]);
            $this->following = true;
        } catch (PlanLimitExceeded $e) {
            // Bubble the trigger to the site-wide UpgradePrompt modal,
            // which listens for this event and opens with the matching
            // copy.
            $this->dispatch('open-upgrade-prompt', trigger: $e->trigger);
        }
    }

    public function render()
    {
        return view('livewire.follow-button');
    }

    #[On('upgrade-prompt-follow-refresh')]
    public function refresh(): void
    {
        $this->following = $this->currentFollow()?->exists ?? false;
    }

    private function currentFollow(): ?Follow
    {
        $userId = auth()->id();

        if ($userId === null) {
            return null;
        }

        return Follow::query()
            ->where('user_id', $userId)
            ->where('followable_type', $this->followableType)
            ->where('followable_id', $this->followableId)
            ->first();
    }

    private function resolve(): ?Model
    {
        return match ($this->followableType) {
            'organisation' => Organisation::query()->find($this->followableId),
            'venue' => Venue::query()->find($this->followableId),
            'discipline' => Discipline::query()->find($this->followableId),
            default => null,
        };
    }
}
