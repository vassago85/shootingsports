<?php

namespace App\Livewire\Settings;

use App\Enums\EmailCategory;
use App\Models\User;
use App\Support\EmailPreferences;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Authenticated email-preferences page. All four opt-in-able
 * categories map to a single checkbox that binds directly to the
 * matching users column. Saving is explicit (button, not live) so a
 * user can toggle multiple boxes and see one confirmation instead of
 * five.
 *
 * The transactional row is deliberately rendered as a static
 * "always on" tile (no checkbox) — makes it clear we _can't_ turn
 * these off without breaking the service, so nobody wastes time
 * looking for a hidden toggle.
 */
class NotificationPreferences extends Component
{
    public bool $marketingMaster = true;

    public bool $matchAlerts = true;

    public bool $weeklyDigest = false;

    public bool $productUpdates = true;

    public bool $trialNudges = true;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->marketingMaster = (bool) $user->emails_marketing_enabled;
        $this->matchAlerts = (bool) $user->emails_match_alerts_enabled;
        $this->weeklyDigest = (bool) $user->emails_weekly_digest_enabled;
        $this->productUpdates = (bool) $user->emails_product_updates_enabled;
        $this->trialNudges = (bool) $user->emails_trial_nudges_enabled;
    }

    public function save(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $user->forceFill([
            'emails_marketing_enabled' => $this->marketingMaster,
            'emails_match_alerts_enabled' => $this->matchAlerts,
            'emails_weekly_digest_enabled' => $this->weeklyDigest,
            'emails_product_updates_enabled' => $this->productUpdates,
            'emails_trial_nudges_enabled' => $this->trialNudges,
        ])->save();

        // If they toggled the master back on from this page, clear the
        // unsubscribed_at audit stamp. If they turned it off, stamp it
        // with the settings-page source so we can distinguish from an
        // email-link click.
        if ($this->marketingMaster) {
            if ($user->unsubscribed_at !== null) {
                EmailPreferences::resubscribeToAll($user);
            }
        } else {
            EmailPreferences::unsubscribeFromAll($user, 'settings_page');
        }

        session()->flash('status', 'Preferences saved.');
    }

    #[Layout('components.layouts.public', ['title' => 'Email preferences'])]
    public function render()
    {
        return view('livewire.settings.notification-preferences', [
            'categories' => EmailCategory::cases(),
        ]);
    }
}
