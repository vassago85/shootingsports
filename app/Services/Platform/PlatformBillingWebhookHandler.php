<?php

namespace App\Services\Platform;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformSubscription;
use App\Support\PlatformFees;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Translates Paystack subscription lifecycle events into the
 * PlatformSubscription status machine and the PlatformPayment ledger.
 *
 * Pure PHP — signature verification, event dedup and transactions live
 * in PaystackController::webhook(), which delegates here. Events that
 * match no PlatformSubscription are ignored silently.
 */
class PlatformBillingWebhookHandler
{
    public const PURPOSE = 'platform_subscription';

    private const INVOICE_NUMBER_ATTEMPTS = 3;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $eventType, array $data): void
    {
        match ($eventType) {
            'charge.success' => $this->onChargeSuccess($data),
            'subscription.create' => $this->onSubscriptionCreate($data),
            'subscription.disable', 'subscription.not_renew' => $this->onSubscriptionCancelled($data),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($data),
            'invoice.update' => $this->onInvoiceUpdate($data),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onChargeSuccess(array $data): void
    {
        $purpose = $this->metadata($data)['purpose'] ?? null;

        if ($purpose !== null && $purpose !== self::PURPOSE) {
            return;
        }

        $reference = (string) data_get($data, 'reference', '');
        $subscription = $this->findByReference($reference) ?? $this->findByMetadataId($data);

        if (! $subscription instanceof PlatformSubscription || $reference === '') {
            return;
        }

        $this->recordPaymentAndActivate($subscription, $reference, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onSubscriptionCreate(array $data): void
    {
        $customerCode = (string) data_get($data, 'customer.customer_code', '');

        $subscription = $this->findByMetadataId($data)
            ?? $this->findByReference((string) data_get($data, 'reference', ''))
            ?? ($customerCode !== '' ? PlatformSubscription::query()->where('paystack_customer_code', $customerCode)->whereNull('paystack_subscription_code')->first() : null);

        if (! $subscription instanceof PlatformSubscription) {
            return;
        }

        $subscriptionCode = (string) data_get($data, 'subscription_code', '');
        $emailToken = (string) data_get($data, 'email_token', '');

        $subscription->forceFill([
            'paystack_subscription_code' => $subscriptionCode !== '' ? $subscriptionCode : $subscription->paystack_subscription_code,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $subscription->paystack_customer_code,
            'paystack_email_token' => $emailToken !== '' ? $emailToken : $subscription->paystack_email_token,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onSubscriptionCancelled(array $data): void
    {
        $subscription = $this->findBySubscriptionCode($data);

        if (! $subscription instanceof PlatformSubscription) {
            return;
        }

        $subscription->forceFill([
            'status' => PlatformSubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onInvoicePaymentFailed(array $data): void
    {
        $subscription = $this->findBySubscriptionCode($data);

        if (! $subscription instanceof PlatformSubscription) {
            return;
        }

        $subscription->forceFill(['status' => PlatformSubscriptionStatus::PastDue])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onInvoiceUpdate(array $data): void
    {
        $succeeded = (string) data_get($data, 'status', '') === 'success' || data_get($data, 'paid') === true;

        if (! $succeeded) {
            return;
        }

        $subscription = $this->findBySubscriptionCode($data);
        $reference = (string) (data_get($data, 'transaction.reference') ?: data_get($data, 'invoice_code', ''));

        if (! $subscription instanceof PlatformSubscription || $reference === '') {
            return;
        }

        $this->recordPaymentAndActivate($subscription, $reference, $data);
    }

    /**
     * Idempotent per Paystack reference: the first charge arrives as both
     * charge.success and invoice.update, and Paystack retries deliveries.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordPaymentAndActivate(PlatformSubscription $subscription, string $reference, array $data): void
    {
        if (PlatformPayment::query()->where('paystack_reference', $reference)->exists()) {
            return;
        }

        $periodStart = $subscription->current_period_end !== null && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end->copy()
            : today();

        $customerCode = (string) data_get($data, 'customer.customer_code', '');

        $subscription->forceFill([
            'status' => PlatformSubscriptionStatus::Active,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $subscription->paystack_customer_code,
            'current_period_start' => $periodStart,
            'current_period_end' => $this->advance($periodStart, $subscription->interval),
            'dunning_retry_count' => 0,
            'next_retry_on' => null,
        ])->save();

        $payment = $subscription->payments()->create([
            'paystack_reference' => $reference,
            'amount_cents' => (int) data_get($data, 'amount', 0),
            'currency' => (string) (data_get($data, 'currency') ?: 'ZAR'),
            'paid_at' => $this->paidAt($data),
        ]);

        $this->issueInvoice($subscription, $payment, $periodStart);
    }

    /**
     * Write the SA VAT tax invoice for a subscription charge. The charged
     * amount is VAT-inclusive, so the exclusive amount and VAT are derived
     * from it at the VAT rate in force today (snapshotted on the invoice).
     *
     * The number is allocated as the next in the issue year; a unique
     * violation from a concurrent writer bumps the counter and retries.
     */
    private function issueInvoice(PlatformSubscription $subscription, PlatformPayment $payment, CarbonInterface $periodStart): void
    {
        if (PlatformInvoice::query()->where('platform_payment_id', $payment->id)->exists()) {
            return;
        }

        $vatBps = PlatformFees::vatBps();
        $inclusiveCents = $payment->amount_cents;
        $exclusiveCents = (int) round($inclusiveCents * 10000 / (10000 + $vatBps));
        $issuedOn = today();
        $description = "{$subscription->tier->label()} tier — {$subscription->interval->label()} subscription ({$periodStart->format('Y-m')})";

        $attributes = [
            'organisation_id' => $subscription->organisation_id,
            'platform_subscription_id' => $subscription->id,
            'platform_payment_id' => $payment->id,
            'issued_on' => $issuedOn,
            'period_start' => $periodStart,
            'period_end' => $subscription->current_period_end,
            'description' => $description,
            'amount_excl_vat_cents' => $exclusiveCents,
            'vat_cents' => $inclusiveCents - $exclusiveCents,
            'amount_incl_vat_cents' => $inclusiveCents,
            'vat_bps' => $vatBps,
            'currency' => $payment->currency,
            'paystack_reference' => $payment->paystack_reference,
        ];

        $attempt = 0;

        while (true) {
            try {
                DB::transaction(fn () => PlatformInvoice::query()->create([
                    ...$attributes,
                    'invoice_number' => $this->nextInvoiceNumber($issuedOn->year, $attempt),
                ]));

                return;
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempt >= self::INVOICE_NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function nextInvoiceNumber(int $year, int $skip): string
    {
        $latest = PlatformInvoice::query()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->max('invoice_number');

        $sequence = $latest === null ? 0 : (int) substr((string) $latest, -6);

        return sprintf('INV-%d-%06d', $year, $sequence + 1 + $skip);
    }

    private function advance(CarbonInterface $from, BillingInterval $interval): CarbonInterface
    {
        return match ($interval) {
            BillingInterval::Monthly => $from->copy()->addMonth(),
            BillingInterval::Annual => $from->copy()->addYear(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paidAt(array $data): CarbonInterface
    {
        $paidAt = data_get($data, 'paid_at');

        if (! is_string($paidAt) || $paidAt === '') {
            return now();
        }

        try {
            return Carbon::parse($paidAt);
        } catch (\Throwable) {
            Log::warning('Platform billing: unparseable paid_at on Paystack payload', ['paid_at' => $paidAt]);

            return now();
        }
    }

    private function findByReference(string $reference): ?PlatformSubscription
    {
        if ($reference === '') {
            return null;
        }

        return PlatformSubscription::query()->where('paystack_reference', $reference)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findByMetadataId(array $data): ?PlatformSubscription
    {
        $id = (int) ($this->metadata($data)['subscription_id'] ?? 0);

        return $id > 0 ? PlatformSubscription::query()->find($id) : null;
    }

    /**
     * Subscription events carry the code at the top level; invoice events
     * nest it under `subscription`.
     *
     * @param  array<string, mixed>  $data
     */
    private function findBySubscriptionCode(array $data): ?PlatformSubscription
    {
        $code = (string) (data_get($data, 'subscription_code') ?: data_get($data, 'subscription.subscription_code', ''));

        if ($code === '') {
            return null;
        }

        return PlatformSubscription::query()->where('paystack_subscription_code', $code)->first();
    }

    /**
     * Paystack sends metadata as an object or, occasionally, a JSON string.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadata(array $data): array
    {
        $metadata = $data['metadata'] ?? null;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        return is_array($metadata) ? $metadata : [];
    }
}
