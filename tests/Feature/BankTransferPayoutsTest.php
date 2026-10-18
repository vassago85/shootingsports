<?php

use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\ClubPayable;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\PlatformPayout;
use App\Models\User;
use App\Services\Platform\RequestPayout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.paystack.secret_key' => 'sk_test_dummy']);
});

/**
 * @return array{0: Organisation, 1: MembershipInvoice}
 */
function payoutOrgWithIssuedInvoice(string $payoutMethod, ?string $subaccountId = null): array
{
    $org = Organisation::factory()->create([
        'payout_method' => $payoutMethod,
        'paystack_subaccount_id' => $subaccountId,
    ]);
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $org->id,
        'user_id' => User::factory()->create()->id,
    ]);
    $invoice = MembershipInvoice::factory()->for($member, 'clubMember')->for($org)->create([
        'amount_cents' => 60000,
        'status' => MembershipInvoiceStatus::Issued,
        'paystack_reference' => 'ref_'.$org->id,
    ]);

    return [$org, $invoice];
}

function fireMembershipChargeSuccess(mixed $test, MembershipInvoice $invoice, string $eventId, bool $stampFee = true): void
{
    $metadata = ['purpose' => 'club_membership', 'invoice_id' => $invoice->id];

    if ($stampFee) {
        $metadata['platform_fee_cents'] = 1200;
        $metadata['platform_fee_vat_cents'] = 180;
    }

    $body = json_encode([
        'id' => $eventId,
        'event' => 'charge.success',
        'data' => [
            'reference' => $invoice->paystack_reference,
            'amount' => 60000,
            'fees' => 1500,
            'metadata' => $metadata,
        ],
    ]);

    $test->call(
        method: 'POST',
        uri: '/paystack/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
        ],
        content: $body,
    )->assertOk();
}

it('writes a ClubPayable when a bank_transfer org receives a Paystack membership payment via webhook', function () {
    [$org, $invoice] = payoutOrgWithIssuedInvoice('bank_transfer');

    fireMembershipChargeSuccess($this, $invoice, 'evt_bt_1');

    $payable = ClubPayable::query()->where('membership_invoice_id', $invoice->id)->firstOrFail();

    expect($payable->organisation_id)->toBe($org->id)
        ->and($payable->gross_cents)->toBe(60000)
        ->and($payable->platform_fee_cents)->toBe(1200)
        ->and($payable->platform_fee_vat_cents)->toBe(180)
        ->and($payable->gateway_fee_cents)->toBe(1500)
        ->and($payable->net_due_cents)->toBe(60000 - 1200 - 180 - 1500)
        ->and($payable->paid_out)->toBeFalse()
        ->and($payable->occurred_at)->not->toBeNull();
});

it('does not write a ClubPayable when the org has a Paystack subaccount', function () {
    [, $invoice] = payoutOrgWithIssuedInvoice('paystack_subaccount', 'ACCT_xxx');

    fireMembershipChargeSuccess($this, $invoice, 'evt_sub_1');

    expect($invoice->fresh()->status)->toBe(MembershipInvoiceStatus::Paid)
        ->and(ClubPayable::count())->toBe(0);
});

it('is idempotent: a webhook retry does not duplicate the ClubPayable', function () {
    [, $invoice] = payoutOrgWithIssuedInvoice('bank_transfer');

    fireMembershipChargeSuccess($this, $invoice, 'evt_retry_1');
    fireMembershipChargeSuccess($this, $invoice, 'evt_retry_2');

    expect(ClubPayable::where('membership_invoice_id', $invoice->id)->count())->toBe(1);
});

it('skips the payout admin fee for the first payout in a calendar month', function () {
    config()->set('platform.payout_admin_fee_cents', 5000);

    $org = Organisation::factory()->create();
    ClubPayable::factory()->for($org)->count(3)->create(['net_due_cents' => 10000, 'paid_out' => false]);

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->subtotal_cents)->toBe(30000)
        ->and($payout->admin_fee_cents)->toBe(0)
        ->and($payout->admin_fee_vat_cents)->toBe(0)
        ->and($payout->net_cents)->toBe(30000);
});

it('deducts the payout admin fee from the 2nd+ payout in the same calendar month', function () {
    config()->set('platform.payout_admin_fee_cents', 5000);
    config()->set('platform.vat_bps', 1500);

    $org = Organisation::factory()->create();
    PlatformPayout::factory()->for($org)->create(['created_at' => now()->startOfMonth()->addSecond()]);

    ClubPayable::factory()->for($org)->create(['net_due_cents' => 10000, 'paid_out' => false]);

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->admin_fee_cents)->toBe(5000)
        ->and($payout->admin_fee_vat_cents)->toBe(750)
        ->and($payout->net_cents)->toBe(10000 - 5000 - 750);
});

it('RequestPayout marks all payables paid_out and links them to the created payout', function () {
    $org = Organisation::factory()->create();
    $payables = ClubPayable::factory()->for($org)->count(2)->create();
    $otherOrgPayable = ClubPayable::factory()->create();

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->payables)->toHaveCount(2);

    foreach ($payables as $payable) {
        $fresh = $payable->fresh();
        expect($fresh->paid_out)->toBeTrue()
            ->and($fresh->platform_payout_id)->toBe($payout->id);
    }

    expect($otherOrgPayable->fresh()->paid_out)->toBeFalse()
        ->and($otherOrgPayable->fresh()->platform_payout_id)->toBeNull();
});

it('RequestPayout refuses when there is nothing outstanding', function () {
    $org = Organisation::factory()->create();
    ClubPayable::factory()->for($org)->paidOut()->create();

    app(RequestPayout::class)->for($org);
})->throws(RuntimeException::class);

it('a real Paystack checkout for a bank_transfer club produces a ClubPayable with the platform fee deducted', function () {
    config([
        'platform.fee_bps' => 200,
        'platform.vat_bps' => 1500,
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    [$org, $invoice] = payoutOrgWithIssuedInvoice('bank_transfer');
    $org->forceFill(['membership_online_payments' => true])->save();
    $user = $invoice->clubMember->user;

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/bank',
                'access_code' => 'bank',
                'reference' => 'ref_e2e_bank',
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('my-memberships.paystack', $invoice))
        ->assertRedirect('https://checkout.paystack.com/bank');

    $stampedMetadata = null;
    Http::assertSent(function ($request) use (&$stampedMetadata) {
        $stampedMetadata = $request['metadata'];

        return true;
    });

    $body = json_encode([
        'id' => 'evt_e2e_bank',
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_e2e_bank',
            'amount' => 60000,
            'fees' => 1500,
            'metadata' => $stampedMetadata,
        ],
    ]);

    $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
        ],
        content: $body,
    )->assertOk();

    $payable = ClubPayable::query()->where('membership_invoice_id', $invoice->id)->firstOrFail();

    expect($payable->platform_fee_cents)->toBe(1200)
        ->and($payable->platform_fee_vat_cents)->toBe(180)
        ->and($payable->net_due_cents)->toBe(60000 - 1500 - 1200 - 180);
});

it('cancelled payouts do not count towards the first-payout-of-month rule', function () {
    config()->set('platform.payout_admin_fee_cents', 5000);

    $org = Organisation::factory()->create();
    PlatformPayout::factory()->for($org)->create([
        'status' => 'cancelled',
        'created_at' => now()->startOfMonth()->addSecond(),
    ]);
    ClubPayable::factory()->for($org)->create(['net_due_cents' => 10000, 'paid_out' => false]);

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->admin_fee_cents)->toBe(0);
});

it('BatchClubPayouts command exports pending payouts to CSV and marks them exported with --mark-exported', function () {
    $org = Organisation::factory()->create(['name' => 'Alpha Rifle Club']);
    $pending = PlatformPayout::factory()->for($org)->create(['net_cents' => 12345]);
    $alreadyExported = PlatformPayout::factory()->create(['status' => 'exported']);

    Artisan::call('platform:batch-club-payouts');
    $csv = Artisan::output();

    expect($csv)->toContain('club,account_name,account_number,bank_code,amount_cents,amount_rands,payout_id')
        ->and($csv)->toContain('"Alpha Rifle Club",TBD,TBD,TBD,12345,123.45,'.$pending->id)
        ->and($csv)->not->toContain((string) $alreadyExported->organisation->name)
        ->and($pending->fresh()->status)->toBe('pending');

    Artisan::call('platform:batch-club-payouts', ['--mark-exported' => true]);

    $pending->refresh();

    expect($pending->status)->toBe('exported')
        ->and($pending->exported_at)->not->toBeNull();
});
