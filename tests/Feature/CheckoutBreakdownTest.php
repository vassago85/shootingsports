<?php

use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PlatformFees;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'platform.fee_bps' => 200,
        'platform.vat_bps' => 1500,
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);
});

/**
 * @return array{0: User, 1: Organisation, 2: ClubMember, 3: MembershipInvoice}
 */
function memberWithIssuedInvoice(?string $subaccountId = 'ACCT_abc'): array
{
    $user = User::factory()->create();
    $org = Organisation::factory()->create([
        'name' => 'Pretoria Rifle Club',
        'paystack_subaccount_id' => $subaccountId,
        'membership_online_payments' => true,
    ]);
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $org->id,
        'user_id' => $user->id,
    ]);
    $invoice = MembershipInvoice::factory()->for($member, 'clubMember')->for($org)->create([
        'amount_cents' => 60000,
        'status' => MembershipInvoiceStatus::Issued,
    ]);

    return [$user, $org, $member, $invoice];
}

it('shows the three-line breakdown on the member checkout page', function () {
    [$user, , $member] = memberWithIssuedInvoice();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('my-memberships.show', $member))
        ->assertOk()
        ->assertSee('Pretoria Rifle Club')
        ->assertSee('R586.20')
        ->assertSee('R13.80')
        ->assertSee('R600.00');
});

it('does not show the breakdown for a club without a subaccount', function () {
    [$user, , $member] = memberWithIssuedInvoice(null);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('my-memberships.show', $member))
        ->assertOk()
        ->assertDontSee('Payment breakdown');
});

it('initialises the Paystack transaction with the subaccount split', function () {
    [$user, , , $invoice] = memberWithIssuedInvoice();

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'abc',
                'reference' => 'ref_split',
            ],
        ]),
    ]);

    $split = PlatformFees::splitFor(60000);
    $expectedCharge = $split['fee'] + $split['fee_vat'];

    $this->actingAs($user)
        ->post(route('my-memberships.paystack', $invoice))
        ->assertRedirect('https://checkout.paystack.com/abc');

    Http::assertSent(fn ($request) => $request['subaccount'] === 'ACCT_abc'
        && $request['transaction_charge'] === $expectedCharge
        && $request['transaction_charge'] === 1380
        && $request['bearer'] === 'subaccount');
});

it('omits the subaccount params when the club has no subaccount', function () {
    [$user, , , $invoice] = memberWithIssuedInvoice(null);

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/plain',
                'access_code' => 'plain',
                'reference' => 'ref_plain',
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('my-memberships.paystack', $invoice))
        ->assertRedirect('https://checkout.paystack.com/plain');

    Http::assertSent(fn ($request) => ! isset($request['subaccount'])
        && ! isset($request['transaction_charge'])
        && ! isset($request['bearer']));
});

it('populates the four fee columns when the charge.success webhook lands', function () {
    [, , , $invoice] = memberWithIssuedInvoice();
    $invoice->forceFill(['paystack_reference' => 'ref_hook'])->save();

    $body = json_encode([
        'id' => 'evt_checkout_1',
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_hook',
            'amount' => 60000,
            'fees' => 1500,
            'metadata' => [
                'purpose' => 'club_membership',
                'invoice_id' => $invoice->id,
            ],
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

    $fee = PlatformFees::feeCents(60000);
    $feeVat = PlatformFees::feeVatCents($fee);

    $invoice = $invoice->fresh();

    expect($invoice->status)->toBe(MembershipInvoiceStatus::Paid)
        ->and($invoice->gateway_fee_cents)->toBe(1500)
        ->and($invoice->platform_fee_cents)->toBe($fee)
        ->and($invoice->platform_fee_vat_cents)->toBe($feeVat)
        ->and($invoice->net_to_club_cents)->toBe(60000 - 1500 - $fee - $feeVat);
});

it('renders the agent receipt with the Section 54 breakdown', function () {
    [, $org, , $invoice] = memberWithIssuedInvoice();
    $invoice->forceFill([
        'platform_fee_cents' => 1200,
        'platform_fee_vat_cents' => 180,
        'paystack_reference' => 'ref_receipt',
        'paid_at' => now(),
    ])->save();

    $html = view('receipts.membership-agent-receipt', [
        'organisation' => $org,
        'invoice' => $invoice->fresh(),
    ])->render();

    expect($html)
        ->toContain('Receipt from Pretoria Rifle Club')
        ->toContain('Section 54')
        ->toContain('R586.20')
        ->toContain('R12.00')
        ->toContain('R1.80')
        ->toContain('R600.00')
        ->toContain('ref_receipt');
});
