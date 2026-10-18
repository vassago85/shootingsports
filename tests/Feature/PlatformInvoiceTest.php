<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Models\PlatformInvoice;
use App\Models\PlatformSubscription;
use Illuminate\Database\QueryException;

/**
 * Fire a signed platform_subscription charge.success through the real webhook route.
 */
function fireChargeSuccess(PlatformSubscription $subscription, string $reference, int $amountCents, ?string $eventId = null): void
{
    config(['services.paystack.secret_key' => 'sk_test_dummy']);

    $body = json_encode([
        'id' => $eventId ?? 'evt_'.$reference,
        'event' => 'charge.success',
        'data' => [
            'reference' => $reference,
            'amount' => $amountCents,
            'metadata' => [
                'purpose' => 'platform_subscription',
                'subscription_id' => $subscription->id,
            ],
        ],
    ]);

    test()->call(
        method: 'POST',
        uri: '/paystack/webhook',
        parameters: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
        ],
        content: $body,
    )->assertOk();
}

it('issues an invoice when a platform subscription charge succeeds', function () {
    $sub = PlatformSubscription::factory()->trial()->create([
        'tier' => SubscriptionTier::Standard,
        'interval' => BillingInterval::Monthly,
        'paystack_reference' => 'ref_inv_1',
    ]);

    fireChargeSuccess($sub, 'ref_inv_1', 57500);

    $payment = $sub->payments()->first();
    $invoice = PlatformInvoice::query()->sole();

    expect($invoice->platform_payment_id)->toBe($payment->id)
        ->and($invoice->platform_subscription_id)->toBe($sub->id)
        ->and($invoice->organisation_id)->toBe($sub->organisation_id)
        ->and($invoice->paystack_reference)->toBe('ref_inv_1')
        ->and($invoice->amount_incl_vat_cents)->toBe(57500)
        ->and($invoice->amount_excl_vat_cents)->toBe(50000)
        ->and($invoice->vat_cents)->toBe(7500)
        ->and($invoice->vat_bps)->toBe(1500)
        ->and($invoice->currency)->toBe('ZAR')
        ->and($invoice->issued_on->toDateString())->toBe(today()->toDateString())
        ->and($invoice->period_start->toDateString())->toBe($sub->fresh()->current_period_start->toDateString())
        ->and($invoice->period_end->toDateString())->toBe($sub->fresh()->current_period_end->toDateString())
        ->and($invoice->description)->toBe('Standard tier — Monthly subscription ('.today()->format('Y-m').')');
});

it('derives the VAT split from amount_incl using the current vat_bps', function () {
    $sub = PlatformSubscription::factory()->trial()->create();

    fireChargeSuccess($sub, 'ref_vat_15', 115000);

    config(['platform.vat_bps' => 1000]);
    fireChargeSuccess($sub, 'ref_vat_10', 110000);

    $standard = PlatformInvoice::query()->where('paystack_reference', 'ref_vat_15')->sole();
    $reduced = PlatformInvoice::query()->where('paystack_reference', 'ref_vat_10')->sole();

    expect($standard->amount_excl_vat_cents)->toBe(100000)
        ->and($standard->vat_cents)->toBe(15000)
        ->and($standard->vat_bps)->toBe(1500)
        ->and($reduced->amount_excl_vat_cents)->toBe(100000)
        ->and($reduced->vat_cents)->toBe(10000)
        ->and($reduced->vat_bps)->toBe(1000);
});

it('does not duplicate the invoice on webhook retry', function () {
    $sub = PlatformSubscription::factory()->trial()->create();

    fireChargeSuccess($sub, 'ref_retry', 11500, 'evt_retry_1');
    fireChargeSuccess($sub, 'ref_retry', 11500, 'evt_retry_2');

    expect(PlatformInvoice::query()->count())->toBe(1)
        ->and($sub->payments()->count())->toBe(1);
});

it('numbers invoices monotonically within a year', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $sub = PlatformSubscription::factory()->trial()->create();

    fireChargeSuccess($sub, 'ref_mono_1', 11500);
    fireChargeSuccess($sub, 'ref_mono_2', 11500);

    expect(PlatformInvoice::query()->orderBy('id')->pluck('invoice_number')->all())
        ->toBe(['INV-2026-000001', 'INV-2026-000002']);
});

it('resets the invoice number on the year boundary', function () {
    $sub = PlatformSubscription::factory()->trial()->create();

    $this->travelTo('2026-12-15 09:00:00');
    fireChargeSuccess($sub, 'ref_year_1', 11500);

    $this->travelTo('2027-01-05 09:00:00');
    fireChargeSuccess($sub, 'ref_year_2', 11500);

    expect(PlatformInvoice::query()->orderBy('id')->pluck('invoice_number')->all())
        ->toBe(['INV-2026-000001', 'INV-2027-000001']);
});

it('renders the tax invoice template with all required SA VAT elements', function () {
    config([
        'services.sars.vat_number' => '4123456789',
        'services.sars.company_address' => '1 Test Street, Pretoria',
    ]);

    $invoice = PlatformInvoice::factory()->create([
        'invoice_number' => 'INV-2026-000042',
        'amount_excl_vat_cents' => 100000,
        'vat_cents' => 15000,
        'amount_incl_vat_cents' => 115000,
        'vat_bps' => 1500,
        'paystack_reference' => 'ref_render',
    ]);

    $html = view('invoices.platform-subscription-tax-invoice', ['invoice' => $invoice])->render();

    expect($html)
        ->toContain('TAX INVOICE')
        ->toContain('shootingsports.co.za')
        ->toContain('1 Test Street, Pretoria')
        ->toContain('VAT no. 4123456789')
        ->toContain('INV-2026-000042')
        ->toContain($invoice->organisation->name)
        ->toContain($invoice->description)
        ->toContain('ref_render')
        ->toContain('R1,000.00')
        ->toContain('VAT (15%)')
        ->toContain('R150.00')
        ->toContain('R1,150.00');
});

it('two invoices for the same platform payment are impossible at the DB level', function () {
    $first = PlatformInvoice::factory()->create();

    expect(fn () => PlatformInvoice::factory()->create([
        'organisation_id' => $first->organisation_id,
        'platform_subscription_id' => $first->platform_subscription_id,
        'platform_payment_id' => $first->platform_payment_id,
    ]))->toThrow(QueryException::class);

    expect(PlatformInvoice::query()->count())->toBe(1);
});
