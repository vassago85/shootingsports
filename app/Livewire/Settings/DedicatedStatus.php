<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dedicated-status tracker setup page.
 *
 * The shooter enters their accrediting body, next renewal date and the
 * number of activity-counting matches required between renewals. The
 * tracker widget on /me/log and /me picks these up and shows progress.
 *
 * We deliberately do not hardcode body names or required counts here —
 * requirements differ between associations and the numbers shift over
 * time. A free-text body field plus a free integer count is the
 * honest shape.
 *
 * The setup form is visible to non-Pro shooters too, with a notice
 * that the tracker only renders on Pro. That lets a trial-ready
 * shooter prepare their fields now so the moment Pro opens the
 * progress widget just appears.
 */
class DedicatedStatus extends Component
{
    public string $body = '';

    public ?string $renewalOn = null;

    public ?int $requiredCount = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->body = (string) ($user->dedicated_body ?? '');
        $this->renewalOn = $user->dedicated_renewal_on?->format('Y-m-d');
        $this->requiredCount = $user->dedicated_required_count;
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'body' => ['nullable', 'string', 'max:120'],
            'renewalOn' => ['nullable', 'date'],
            'requiredCount' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user->forceFill([
            'dedicated_body' => $data['body'] !== '' ? $data['body'] : null,
            'dedicated_renewal_on' => $data['renewalOn'] ?: null,
            'dedicated_required_count' => $data['requiredCount'] ?: null,
        ])->save();

        session()->flash('status', 'Dedicated-status settings saved.');
    }

    public function clear(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $user->forceFill([
            'dedicated_body' => null,
            'dedicated_renewal_on' => null,
            'dedicated_required_count' => null,
        ])->save();

        $this->body = '';
        $this->renewalOn = null;
        $this->requiredCount = null;

        session()->flash('status', 'Dedicated-status settings cleared.');
    }

    #[Layout('components.layouts.public', ['title' => 'Dedicated status'])]
    public function render()
    {
        return view('livewire.settings.dedicated-status', [
            'user' => auth()->user(),
        ]);
    }
}
