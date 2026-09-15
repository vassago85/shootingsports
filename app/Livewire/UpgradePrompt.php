<?php

namespace App\Livewire;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Inert Pro upgrade demand-capture. Rendered once in the public layout
 * as a modal + listener. Never opens on load, never on sign-up, never
 * in Discover — always opened by an explicit event (over-limit action,
 * a Pro-only badge tap, the history cut-off).
 *
 * When rendered with as="cutoff-row" it renders inline (not as a
 * modal) — the /my-calendar past history uses this to name the free
 * horizon without a modal on page load.
 */
class UpgradePrompt extends Component
{
    /** @var string one of follow_limit, saved_search_limit, history_window, export, household, empty string when hidden */
    public string $trigger = '';

    public bool $open = false;

    public bool $submitted = false;

    public string $answer = '';

    public string $as = 'modal';

    public function mount(string $trigger = '', string $as = 'modal'): void
    {
        $this->trigger = $trigger;
        $this->as = $as;
    }

    #[On('open-upgrade-prompt')]
    public function openPrompt(string $trigger): void
    {
        $this->trigger = $trigger;
        $this->open = true;
        $this->submitted = false;
        $this->answer = '';

        $this->dispatch('umami-track', name: 'pro_upgrade_impression', trigger: $trigger);
    }

    public function close(): void
    {
        $this->open = false;
        $this->trigger = '';
        $this->submitted = false;
        $this->answer = '';
    }

    public function notify(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->trigger === '') {
            $this->close();

            return;
        }

        // One waitlist enquiry per (user, trigger). A second tap on
        // the same trigger merges the free-text answer if one is
        // provided, rather than duplicating rows. The prompt says
        // "Notify me" — noise-free demand signal is the point.
        $enquiry = Enquiry::query()
            ->where('type', EnquiryType::ProWaitlist->value)
            ->where('user_id', $user->id)
            ->whereJsonContains('context->trigger', $this->trigger)
            ->first();

        $context = [
            'trigger' => $this->trigger,
        ];

        if (filled($this->answer)) {
            $context['answer'] = $this->answer;
        }

        if ($enquiry) {
            $existing = $enquiry->context ?? [];
            // Preserve the earlier answer if the second tap left the
            // field empty. Overwrite when the user actually typed.
            if (filled($this->answer)) {
                $existing['answer'] = $this->answer;
            }
            $existing['trigger'] = $this->trigger;
            $enquiry->forceFill(['context' => $existing])->save();
        } else {
            Enquiry::create([
                'type' => EnquiryType::ProWaitlist,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'subject' => 'Pro waitlist: '.$this->trigger,
                'body' => 'Signed up from the '.$this->trigger.' upgrade prompt.',
                'context' => $context,
                'status' => EnquiryStatus::New,
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        }

        $this->submitted = true;

        // Refresh state of any FollowButton on the same page so the
        // toggled-off state does not stick after the user upgrades.
        $this->dispatch('upgrade-prompt-follow-refresh');

        $this->dispatch('umami-track', name: 'pro_upgrade_notify', trigger: $this->trigger);
    }

    /**
     * Copy per trigger. Kept in the component so adding a trigger is
     * one place — add the enum-ish key + row, done.
     *
     * @return array{headline: string, detail: string}
     */
    public function copyForTrigger(): array
    {
        return match ($this->trigger) {
            'follow_limit' => [
                'headline' => 'You are following as many clubs and disciplines as Free allows.',
                'detail' => 'Pro removes the 3-follow cap. Follow every club, every discipline, every venue you shoot at and keep them all in one feed.',
            ],
            'saved_search_limit' => [
                'headline' => 'You already have your one saved search.',
                'detail' => 'Pro lets you save as many filter sets as you want — one for each discipline, province and driving distance you actually chase.',
            ],
            'history_window' => [
                'headline' => 'Your Free calendar remembers the last 12 months.',
                'detail' => 'Pro keeps every match you have ever added, so the year-end recap and last-season lookup are always there.',
            ],
            'export' => [
                'headline' => 'Season exports are a Pro feature.',
                'detail' => 'Pro exports your full season as CSV or a printable PDF you can hand to a range officer or your accountant.',
            ],
            'household' => [
                'headline' => 'One household, one Pro subscription.',
                'detail' => 'Pro covers up to four shooters in a household, each with their own saved calendar and follows.',
            ],
            default => [
                'headline' => 'Pro is coming.',
                'detail' => 'Get on the waitlist and tell us what you want it to do.',
            ],
        };
    }

    public function render()
    {
        return view('livewire.upgrade-prompt', [
            'copy' => $this->copyForTrigger(),
            'pricing' => config('plans.pricing', []),
        ]);
    }
}
