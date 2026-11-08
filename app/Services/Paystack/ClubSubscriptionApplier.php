<?php

namespace App\Services\Paystack;

use App\Enums\ClubPlan;
use App\Models\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Puts a club on the Club plan from a Paystack subscription charge.
 * Separate from {@see SubscriptionApplier}, which only touches users.
 */
class ClubSubscriptionApplier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function applyFromCharge(array $data): ?Organisation
    {
        $organisation = $this->resolve($data);

        if (! $organisation instanceof Organisation) {
            Log::warning('Paystack club plan: organisation not found', [
                'reference' => data_get($data, 'reference'),
                'organisation_id' => data_get($data, 'metadata.organisation_id'),
            ]);

            return null;
        }

        $cycle = $this->cycle($data);

        if (blank($organisation->paystack_customer_code)) {
            $this->applyInitial($organisation, $data, $cycle);
        } else {
            $this->applyRenewal($organisation, $cycle);
        }

        return $organisation->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rememberCodes(array $data): ?Organisation
    {
        $organisation = $this->resolve($data);

        if (! $organisation instanceof Organisation) {
            return null;
        }

        $subscriptionCode = (string) data_get($data, 'subscription_code', data_get($data, 'plan_object.subscription_code', ''));
        $customerCode = (string) data_get($data, 'customer.customer_code', '');

        $organisation->forceFill([
            'paystack_subscription_code' => $subscriptionCode !== '' ? $subscriptionCode : $organisation->paystack_subscription_code,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $organisation->paystack_customer_code,
        ])->save();

        return $organisation;
    }

    public function applyCancellation(Organisation $organisation): void
    {
        $organisation->forceFill([
            'plan_cancelled_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyInitial(Organisation $organisation, array $data, string $cycle): void
    {
        $paidAt = $this->parseTimestamp((string) data_get($data, 'paid_at')) ?? now();
        $customerCode = (string) data_get($data, 'customer.customer_code', '');
        $authorizationCode = (string) data_get($data, 'authorization.authorization_code', '');
        $subscriptionCode = (string) data_get($data, 'plan_object.subscription_code', data_get($data, 'subscription_code', ''));

        $organisation->forceFill([
            'plan' => ClubPlan::Club,
            'plan_billing_cycle' => $cycle,
            'plan_expires_at' => $this->addCycle($paidAt, $cycle),
            'plan_cancelled_at' => null,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $organisation->paystack_customer_code,
            'paystack_authorization_code' => $authorizationCode !== '' ? $authorizationCode : $organisation->paystack_authorization_code,
            'paystack_subscription_code' => $subscriptionCode !== '' ? $subscriptionCode : $organisation->paystack_subscription_code,
        ])->save();
    }

    private function applyRenewal(Organisation $organisation, string $cycle): void
    {
        $base = $organisation->plan_expires_at instanceof Carbon && $organisation->plan_expires_at->isFuture()
            ? $organisation->plan_expires_at
            : now();

        $organisation->forceFill([
            'plan' => ClubPlan::Club,
            'plan_billing_cycle' => $cycle,
            'plan_expires_at' => $this->addCycle($base, $cycle),
            'plan_cancelled_at' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolve(array $data): ?Organisation
    {
        $organisationId = (int) data_get($data, 'metadata.organisation_id', 0);

        if ($organisationId > 0) {
            $organisation = Organisation::query()->find($organisationId);

            if ($organisation instanceof Organisation) {
                return $organisation;
            }
        }

        $subscriptionCode = (string) data_get($data, 'subscription_code', data_get($data, 'plan_object.subscription_code', ''));

        if ($subscriptionCode === '') {
            return null;
        }

        return Organisation::query()->where('paystack_subscription_code', $subscriptionCode)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cycle(array $data): string
    {
        $cycle = (string) data_get($data, 'metadata.cycle', '');

        if (in_array($cycle, ['annual', 'monthly'], true)) {
            return $cycle;
        }

        return (string) data_get($data, 'plan.interval', '') === 'annually' ? 'annual' : 'monthly';
    }

    private function addCycle(Carbon $from, string $cycle): Carbon
    {
        return $cycle === 'annual'
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();
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
