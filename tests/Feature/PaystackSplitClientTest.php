<?php

use App\Services\Paystack\PaystackClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.webhook_secret' => null,
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.currency' => 'ZAR',
    ]);
});

it('marks banks that paystack cannot auto-verify', function () {
    Http::preventStrayRequests();

    Http::fake(function (Request $request) {
        $verifying = str_contains($request->url(), 'enabled_for_verification=true');

        return Http::response([
            'status' => true,
            'data' => $verifying
                ? [[
                    'name' => 'ABSA Bank',
                    'code' => '632005',
                    'supported_types' => ['personal', 'business'],
                ]]
                : [
                    ['name' => 'TymeBank', 'code' => '678910'],
                    ['name' => 'ABSA Bank', 'code' => '632005'],
                ],
            'meta' => ['page' => 1, 'pageCount' => 1],
        ]);
    });

    $banks = (new PaystackClient)->listBanks();

    expect($banks)->toBe([
        [
            'name' => 'ABSA Bank',
            'code' => '632005',
            'enabled_for_verification' => true,
            'supported_types' => ['personal', 'business'],
        ],
        [
            'name' => 'TymeBank',
            'code' => '678910',
            'enabled_for_verification' => false,
            'supported_types' => [],
        ],
    ]);
});

it('returns a failed bank check as a message the club can correct', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/bank/validate' => Http::response([
            'status' => true,
            'message' => 'Personal Account Verification attempted',
            'data' => [
                'verified' => false,
                'verificationMessage' => 'Account holder name does not match',
                'accountHolderMatch' => false,
                'accountOpen' => true,
            ],
        ]),
    ]);

    $result = (new PaystackClient)->validateBankAccount(
        bankCode: '632005',
        accountNumber: '0123456789',
        accountName: 'Wrong Name',
        accountType: 'personal',
        documentType: 'identityNumber',
        documentNumber: '8001015009087',
    );

    expect($result['verified'])->toBeFalse()
        ->and($result['message'])->toBe('Account holder name does not match')
        ->and($result['checks']['accountHolderMatch'])->toBeFalse();

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.paystack.co/bank/validate'
            && $body['country_code'] === 'ZA'
            && $body['account_number'] === '0123456789'
            && $body['document_type'] === 'identityNumber';
    });
});

it('returns a verified bank account', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/bank/validate' => Http::response([
            'status' => true,
            'message' => 'Personal Account Verification attempted',
            'data' => [
                'verified' => true,
                'verificationMessage' => 'Account is verified successfully',
            ],
        ]),
    ]);

    $result = (new PaystackClient)->validateBankAccount(
        bankCode: '632005',
        accountNumber: '0123456789',
        accountName: 'Ann Brown',
        accountType: 'personal',
        documentType: 'identityNumber',
        documentNumber: '8001015009087',
    );

    expect($result['verified'])->toBeTrue()
        ->and($result['message'])->toBe('Account is verified successfully');
});

it('creates a subaccount with a zero percentage so the flat fee can override it', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'message' => 'Subaccount created',
            'data' => [
                'subaccount_code' => 'ACCT_newclub',
                'account_number' => '0123456789',
                'is_verified' => false,
                'active' => true,
            ],
        ]),
    ]);

    $result = (new PaystackClient)->syncSubaccount(
        existingCode: null,
        businessName: 'Example Rifle Club',
        bankCode: '632005',
        accountNumber: '0123456789',
        contactEmail: 'club@example.test',
    );

    expect($result)->toBe([
        'subaccount_code' => 'ACCT_newclub',
        'is_verified' => false,
        'active' => true,
    ]);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && $request->url() === 'https://api.paystack.co/subaccount'
            && $body['settlement_bank'] === '632005'
            && $body['account_number'] === '0123456789'
            && $body['percentage_charge'] === 0
            && $body['business_name'] === 'Example Rifle Club';
    });
});

it('updates an existing subaccount instead of creating a second one', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/subaccount/ACCT_existing' => Http::response([
            'status' => true,
            'message' => 'Subaccount updated',
            'data' => [
                'subaccount_code' => 'ACCT_existing',
                'is_verified' => false,
                'active' => true,
            ],
        ]),
    ]);

    $result = (new PaystackClient)->syncSubaccount(
        existingCode: 'ACCT_existing',
        businessName: 'Example Rifle Club',
        bankCode: '250655',
        accountNumber: '9988776655',
    );

    expect($result['subaccount_code'])->toBe('ACCT_existing');

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'PUT'
            && $request->url() === 'https://api.paystack.co/subaccount/ACCT_existing'
            && $body['bank_code'] === '250655'
            && $body['percentage_charge'] === 0;
    });

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('initialises a split with the subaccount, flat charge, and bearer', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/split',
                'access_code' => 'access_split',
                'reference' => 'ss_split_1',
            ],
        ]),
    ]);

    $result = (new PaystackClient)->initializeSplitTransaction(
        email: 'shooter@example.test',
        amountCents: 15000,
        subaccountCode: 'ACCT_club',
        transactionChargeCents: 1150,
        bearer: 'subaccount',
        callbackUrl: 'https://example.test/paystack/callback',
        reference: 'ss_split_1',
        metadata: ['purpose' => 'match_entry'],
    );

    expect($result['authorization_url'])->toBe('https://checkout.paystack.com/split');

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $body['subaccount'] === 'ACCT_club'
            && $body['transaction_charge'] === 1150
            && $body['bearer'] === 'subaccount'
            && $body['amount'] === 15000
            && ! array_key_exists('plan', $body);
    });
});

it('rejects a split whose platform charge is the whole amount', function () {
    Http::preventStrayRequests();

    expect(fn () => (new PaystackClient)->initializeSplitTransaction(
        email: 'shooter@example.test',
        amountCents: 1000,
        subaccountCode: 'ACCT_club',
        transactionChargeCents: 1000,
        bearer: 'account',
        callbackUrl: 'https://example.test/callback',
    ))->toThrow(InvalidArgumentException::class);
});

it('sends a partial refund with a reason and omits the amount on a full refund', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => ['id' => 99, 'status' => 'pending', 'amount' => 5000],
        ]),
    ]);

    (new PaystackClient)->createRefund('ss_split_1', 'Match cancelled', 5000);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $body['transaction'] === 'ss_split_1'
            && $body['amount'] === 5000
            && $body['merchant_note'] === 'Match cancelled'
            && $body['currency'] === 'ZAR';
    });
});

it('omits the amount when the refund is for the full charge', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => ['id' => 100, 'status' => 'pending'],
        ]),
    ]);

    (new PaystackClient)->createRefund('ss_split_1', 'Duplicate entry');

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $body['merchant_note'] === 'Duplicate entry'
            && ! array_key_exists('amount', $body);
    });
});

it('refuses a refund that has no reason', function () {
    Http::preventStrayRequests();

    expect(fn () => (new PaystackClient)->createRefund('ss_split_1', '  '))
        ->toThrow(InvalidArgumentException::class);
});

it('prefers a dedicated webhook secret over the api secret', function () {
    config(['services.paystack.webhook_secret' => 'whsec_dedicated']);

    $body = '{"event":"charge.success"}';

    expect(PaystackClient::webhookSigningSecret())->toBe('whsec_dedicated')
        ->and(PaystackClient::verifyWebhookSignature(
            $body,
            hash_hmac('sha512', $body, 'whsec_dedicated'),
            PaystackClient::webhookSigningSecret(),
        ))->toBeTrue()
        ->and(PaystackClient::verifyWebhookSignature(
            $body,
            hash_hmac('sha512', $body, 'sk_test_dummy'),
            PaystackClient::webhookSigningSecret(),
        ))->toBeFalse();
});
