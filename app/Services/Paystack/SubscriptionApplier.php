<?php

namespace App\Services\Paystack;

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Idempotent "given this Paystack event / verified transaction, put
 * the user in the right subscription state" applier. Used by both the
 * post-checkout callback (instant UX) and the webhook (server-to-
 * server source of truth), so the behaviour is always the same.
 *
 * The plan_expires_at math is deliberately conservative:
 *   - New subscription: start = paid_at, expiry = start + 1 cycle
 *   - Renewal: extend from CURRENT expiry (or now if none), never from
 *     paid_at, so a mid-cycle rebill does not shorten the window.
 *   - Comp grant: expiry = now + N months (handled outside this class
 *     by a Filament action — no Paystack codes touched).
 */
class SubscriptionApplier
{
    /**
     * Called on first successful checkout (from the callback OR from
     * a `charge.success` webhook if the callback did not arrive).
     *
     * @param  User  $user  account to upgrade
     * @param  string  $reference  paystack transaction reference
     * @param  array<string, mixed>  $verified  Paystack /transaction/verify response `data`
     * @param  'annual'|'monthly'  $cycle  chosen billing cycle
     */
    public function applyInitialSubscription(User $user, string $reference, array $verified, string $cycle): void
    {
        $customerCode = (string) data_get($verified, 'customer.customer_code', '');
        $authorizationCode = (string) data_get($verified, 'authorization.authorization_code', '');
        $subscriptionCode = (string) data_get($verified, 'plan_object.subscription_code', '');
        $paidAt = $this->parseTimestamp((string) data_get($verified, 'paid_at')) ?? now();

        $user->forceFill([
            'plan' => Plan::Pro,
            'plan_billing_cycle' => $cycle,
            // First charge starts the entitlement window at paid_at.
            'plan_expires_at' => $this->addCycle($paidAt, $cycle),
            'plan_cancelled_at' => null,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $user->paystack_customer_code,
            'paystack_authorization_code' => $authorizationCode !== '' ? $authorizationCode : $user->paystack_authorization_code,
            'paystack_subscription_code' => $subscriptionCode !== '' ? $subscriptionCode : $user->paystack_subscription_code,
        ])->save();
    }

    /**
     * Called on `charge.success` webhook for a renewal charge. Extends
     * the entitlement window by exactly one cycle from the previous
     * expiry (or now, whichever is later) so a rebill that arrives
     * a day early does not overwrite a day the customer already paid
     * for.
     */
    public function applyRenewal(User $user, string $cycle): void
    {
        $base = $user->plan_expires_at instanceof Carbon && $user->plan_expires_at->isFuture()
            ? $user->plan_expires_at
            : now();

        $user->forceFill([
            'plan' => Plan::Pro,
            'plan_billing_cycle' => $cycle,
            'plan_expires_at' => $this->addCycle($base, $cycle),
            // Renewal wipes any prior "cancelled" state — the customer
            // must have restarted the subscription for the charge to
            // have happened at all.
            'plan_cancelled_at' => null,
        ])->save();
    }

    /**
     * Called on `subscription.disable` and `subscription.not_renew`
     * webhooks. Marks the intent to end without immediately revoking
     * access — Pro stays live until plan_expires_at passes.
     */
    public function applyCancellation(User $user): void
    {
        $user->forceFill([
            'plan_cancelled_at' => now(),
        ])->save();
    }

    /**
     * Grant Pro for a fixed number of months without any Paystack
     * subscription. Used by staff (comp accounts, competition prizes,
     * apologies). No auto-renewal, no paystack codes.
     */
    public function applyManualGrant(User $user, int $months): void
    {
        $base = $user->plan_expires_at instanceof Carbon && $user->plan_expires_at->isFuture()
            ? $user->plan_expires_at
            : now();

        $user->forceFill([
            'plan' => Plan::Pro,
            'plan_billing_cycle' => null,
            'plan_expires_at' => $base->copy()->addMonths($months),
            'plan_cancelled_at' => null,
        ])->save();
    }

    private function addCycle(Carbon $from, string $cycle): Carbon
    {
        return match ($cycle) {
            'annual' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }

    private function parseTimestamp(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
