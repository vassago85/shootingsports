<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Paystack\PaystackClient;
use Livewire\Component;
use RuntimeException;

/**
 * Public /upgrade page. Signed-in Free users see the two plan cards
 * and pick one; on submit we initialise the Paystack transaction with
 * the matching plan code, persist the pending reference against the
 * user, and hand off to Paystack Checkout.
 *
 * Guests get redirected to /login with the intended URL set. Existing
 * Pro users get redirected to /my-calendar with a note — no duplicate
 * subscriptions.
 *
 * The Paystack config gates the whole flow: if the secret key or a
 * plan code is missing (e.g. before setup-plans has been run against
 * a fresh account) the page renders a plain waitlist CTA instead of
 * the checkout button, so the /upgrade URL never 500s in a half-set-up
 * environment.
 */
class Upgrade extends Component
{
    public string $selected = 'annual';

    public function mount(): void
    {
        // Route middleware guarantees an authenticated user; the
        // instanceof guard is just to give the static analyser
        // something to hang on to.
        if (! auth()->user() instanceof User) {
            $this->redirectRoute('login', navigate: false);
        }
    }

    public function pick(string $cycle): void
    {
        $this->selected = in_array($cycle, ['annual', 'monthly'], true) ? $cycle : 'annual';
    }

    public function checkout(PaystackClient $paystack): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login', navigate: false);

            return;
        }

        if (! $this->paystackReady()) {
            session()->flash('status', 'Paystack is not configured yet. Please try again shortly or join the waitlist.');

            return;
        }

        $planCode = (string) config('services.paystack.plan_codes.'.$this->selected);
        $amount = (int) config('plans.pricing.'.$this->selected.'.amount_cents');

        try {
            $init = $paystack->initializeTransaction(
                email: $user->email,
                amountCents: $amount,
                planCode: $planCode,
                callbackUrl: route('paystack.callback'),
                metadata: [
                    'user_id' => $user->id,
                    'cycle' => $this->selected,
                ],
            );
        } catch (RuntimeException|\Throwable $e) {
            report($e);
            session()->flash('status', 'Could not start checkout. Try again in a minute, or email us if it keeps failing.');

            return;
        }

        // Redirect out of the Livewire lifecycle to the Paystack-hosted
        // checkout page. Paystack posts the customer back to
        // /paystack/callback?reference=... on success or cancellation.
        $this->redirect($init['authorization_url'], navigate: false);
    }

    /**
     * True when Paystack is fully configured. The upgrade UI degrades
     * to a "not available yet" state (still counts demand via the
     * existing UpgradePrompt waitlist) when any of these are missing.
     */
    public function paystackReady(): bool
    {
        return filled(config('services.paystack.secret_key'))
            && filled(config('services.paystack.plan_codes.annual'))
            && filled(config('services.paystack.plan_codes.monthly'));
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.upgrade', [
            'user' => $user,
            'pricing' => config('plans.pricing'),
            'ready' => $this->paystackReady(),
        ])->layout('components.layouts.public', ['title' => 'Upgrade to Pro']);
    }
}
