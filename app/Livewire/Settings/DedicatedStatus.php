<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dedicated-status tracker setup page.
 *
 * Captures the fields SAPS DFO expects on a dedicated-status
 * supporting document: accrediting body, membership number, dedicated
 * status number, category, and the minimum activity count the
 * association requires per annual cycle.
 *
 * The annual cycle itself is config-driven (see config/dedicated.php,
 * default 1 November → 31 October) so bodies can shift without a
 * migration or a per-user renewal date.
 *
 * The setup form is visible to non-Pro shooters too, with a Pro
 * notice. The tracker widget and the printable report are both
 * Pro-gated — this lets a trial-ready shooter prepare their fields
 * now so the moment Pro opens the widget just appears.
 */
class DedicatedStatus extends Component
{
    public string $body = '';

    public string $membershipNumber = '';

    public string $statusNumber = '';

    public string $category = '';

    public ?int $requiredCount = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $bodies = $this->bodies();
        $existing = (string) ($user->dedicated_body ?? '');

        // Default new users to NRAPA so the dropdown isn't blank —
        // the brief sets NRAPA as the "preselected for new users"
        // default. An existing valid code wins; anything stale is
        // dropped so the user re-picks explicitly.
        $this->body = array_key_exists($existing, $bodies)
            ? $existing
            : (array_key_first($bodies) ?: '');

        $this->membershipNumber = (string) ($user->dedicated_membership_number ?? '');
        $this->statusNumber = (string) ($user->dedicated_status_number ?? '');
        $this->category = (string) ($user->dedicated_category ?? '');
        $this->requiredCount = $user->dedicated_required_count
            ?? (int) config('dedicated.default_required_count', 2);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate(
            rules: [
                'body' => ['required', Rule::in(array_keys($this->bodies()))],
                'membershipNumber' => ['nullable', 'string', 'max:60'],
                'statusNumber' => ['nullable', 'string', 'max:60'],
                'category' => ['nullable', Rule::in(array_keys($this->categories()))],
                'requiredCount' => [
                    'required',
                    'integer',
                    'min:'.(int) config('dedicated.minimum_required_count', 2),
                    'max:365',
                ],
            ],
            attributes: [
                'body' => 'accrediting body',
                'membershipNumber' => 'membership number',
                'statusNumber' => 'dedicated status number',
                'category' => 'dedicated status category',
                'requiredCount' => 'activities required per cycle',
            ],
        );

        $user->forceFill([
            'dedicated_body' => $data['body'],
            'dedicated_membership_number' => $data['membershipNumber'] !== '' ? $data['membershipNumber'] : null,
            'dedicated_status_number' => $data['statusNumber'] !== '' ? $data['statusNumber'] : null,
            'dedicated_category' => $data['category'] !== '' ? $data['category'] : null,
            'dedicated_required_count' => $data['requiredCount'],
        ])->save();

        session()->flash('status', 'Dedicated-status settings saved.');
    }

    public function clear(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $user->forceFill([
            'dedicated_body' => null,
            'dedicated_membership_number' => null,
            'dedicated_status_number' => null,
            'dedicated_category' => null,
            'dedicated_required_count' => null,
        ])->save();

        $this->body = (string) (array_key_first($this->bodies()) ?: '');
        $this->membershipNumber = '';
        $this->statusNumber = '';
        $this->category = '';
        $this->requiredCount = (int) config('dedicated.default_required_count', 2);

        session()->flash('status', 'Dedicated-status settings cleared.');
    }

    /** @return array<string, string> */
    public function bodies(): array
    {
        return (array) config('dedicated.bodies', []);
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return (array) config('dedicated.categories', []);
    }

    #[Layout('components.layouts.public', ['title' => 'Dedicated status'])]
    public function render()
    {
        return view('livewire.settings.dedicated-status', [
            'user' => auth()->user(),
            'bodies' => $this->bodies(),
            'categories' => $this->categories(),
            'cycle' => auth()->user()?->dedicatedCycle(),
        ]);
    }
}
