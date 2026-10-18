<?php

namespace App\Services\Platform;

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settles the one-off R750 (+VAT) single-event listing fee: records the
 * PlatformPayment, back-links it onto the pre-issued PlatformInvoice and
 * promotes the Draft Event so it appears on the public calendar.
 */
class SingleEventPurchase
{
    public const PURPOSE = 'single_event';

    /**
     * Idempotent per Paystack reference, so webhook retries are harmless.
     *
     * @param  array<string, mixed>  $data  the `data` object of a charge.success webhook
     */
    public function markPaid(array $data): ?PlatformInvoice
    {
        $reference = (string) data_get($data, 'reference', '');

        $invoice = $reference !== ''
            ? PlatformInvoice::query()->where('paystack_reference', $reference)->first()
            : null;

        if (! $invoice instanceof PlatformInvoice) {
            Log::warning('Paystack single_event: invoice not found', ['reference' => $reference]);

            return null;
        }

        if ($invoice->platform_payment_id !== null) {
            return $invoice;
        }

        $amountCents = (int) data_get($data, 'amount', 0);

        if ($amountCents !== $invoice->amount_incl_vat_cents) {
            Log::warning('Paystack single_event: amount mismatch, event left in Draft', [
                'reference' => $reference,
                'expected_cents' => $invoice->amount_incl_vat_cents,
                'paid_cents' => $amountCents,
            ]);

            return null;
        }

        DB::transaction(function () use ($data, $invoice, $reference, $amountCents): void {
            $payment = PlatformPayment::query()->create([
                'platform_subscription_id' => null,
                'paystack_reference' => $reference,
                'amount_cents' => $amountCents,
                'currency' => (string) (data_get($data, 'currency') ?: $invoice->currency),
                'paid_at' => $this->paidAt($data),
            ]);

            $invoice->forceFill(['platform_payment_id' => $payment->id])->save();

            $this->promoteEvent($invoice, (int) data_get($data, 'metadata.event_id', 0));
            $this->publishOrganisation($invoice);
        });

        return $invoice;
    }

    private function promoteEvent(PlatformInvoice $invoice, int $eventId): void
    {
        $event = Event::query()
            ->where('host_organisation_id', $invoice->organisation_id)
            ->where('status', EventStatus::Draft->value)
            ->when($eventId > 0, fn ($query) => $query->whereKey($eventId))
            ->first();

        $event?->update(['status' => EventStatus::Planned]);
    }

    /**
     * Event::scopeUpcoming requires the host organisation to be Published.
     */
    private function publishOrganisation(PlatformInvoice $invoice): void
    {
        Organisation::query()
            ->whereKey($invoice->organisation_id)
            ->where('status', '!=', ListingStatus::Published->value)
            ->first()
            ?->update(['status' => ListingStatus::Published]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paidAt(array $data): Carbon
    {
        $paidAt = data_get($data, 'paid_at');

        if (! is_string($paidAt) || $paidAt === '') {
            return now();
        }

        try {
            return Carbon::parse($paidAt);
        } catch (\Throwable) {
            return now();
        }
    }
}
