<?php

namespace App\Services\Paystack;

use App\Enums\PaystackFeeBearer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Thin, testable wrapper around the handful of Paystack endpoints we
 * actually use. All calls are synchronous — the Paystack API is fast
 * enough (~200ms per call from ZA) and moving to a queue just adds
 * cases where a redirect races an unfinished job.
 *
 * Kept intentionally free of Eloquent — the caller owns persistence.
 * That makes it trivial to Http::fake() in tests and to reuse for
 * the artisan setup command that runs without a User in scope.
 *
 * Docs referenced (Sept 2026):
 *   POST /transaction/initialize   https://paystack.com/docs/api/transaction/
 *   GET  /transaction/verify/:ref  https://paystack.com/docs/api/transaction/
 *   GET  /subscription/:code       https://paystack.com/docs/api/subscription/
 *   POST /subscription/disable     https://paystack.com/docs/api/subscription/
 *   POST /plan                     https://paystack.com/docs/api/plan/
 *   GET  /plan                     https://paystack.com/docs/api/plan/
 *   GET  /bank                     https://paystack.com/docs/api/miscellaneous/
 *   POST /bank/validate            https://paystack.com/docs/api/verification/
 *   POST /subaccount               https://paystack.com/docs/api/subaccount/
 *   PUT  /subaccount/:code         https://paystack.com/docs/api/subaccount/
 *   POST /refund                   https://paystack.com/docs/payments/refunds/
 */
class PaystackClient
{
    private string $baseUrl;

    private string $secretKey;

    public function __construct(?string $secretKey = null, ?string $baseUrl = null)
    {
        $this->secretKey = $secretKey ?? (string) config('services.paystack.secret_key');
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }

    /**
     * Kick off a subscription-forming transaction. Passing `plan` makes
     * Paystack ignore `amount` and charge the plan's amount instead;
     * on success the customer's card is authorised for the recurring
     * schedule and the subscription is created automatically.
     *
     * @return array{authorization_url:string, access_code:string, reference:string}
     */
    public function initializeTransaction(
        string $email,
        int $amountCents,
        string $planCode,
        string $callbackUrl,
        ?string $reference = null,
        array $metadata = [],
    ): array {
        $payload = [
            'email' => $email,
            'amount' => $amountCents,
            'currency' => config('services.paystack.currency', 'ZAR'),
            'plan' => $planCode,
            'callback_url' => $callbackUrl,
            'reference' => $reference ?? $this->generateReference(),
            'metadata' => $metadata,
        ];

        $response = $this->request()
            ->post($this->baseUrl.'/transaction/initialize', $payload)
            ->throw()
            ->json();

        $this->ensureOk($response, 'initializeTransaction');

        return [
            'authorization_url' => (string) ($response['data']['authorization_url'] ?? ''),
            'access_code' => (string) ($response['data']['access_code'] ?? ''),
            'reference' => (string) ($response['data']['reference'] ?? $payload['reference']),
        ];
    }

    /**
     * One-off charge. Unlike {@see initializeTransaction()} this does not
     * attach a subscription plan, so Paystack charges `amount` once.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url:string, access_code:string, reference:string}
     */
    public function initializeOneOffTransaction(
        string $email,
        int $amountCents,
        string $callbackUrl,
        ?string $reference = null,
        array $metadata = [],
    ): array {
        $payload = [
            'email' => $email,
            'amount' => $amountCents,
            'currency' => config('services.paystack.currency', 'ZAR'),
            'callback_url' => $callbackUrl,
            'reference' => $reference ?? $this->generateReference(),
            'metadata' => $metadata,
        ];

        $response = $this->request()
            ->post($this->baseUrl.'/transaction/initialize', $payload)
            ->throw()
            ->json();

        $this->ensureOk($response, 'initializeOneOffTransaction');

        return [
            'authorization_url' => (string) ($response['data']['authorization_url'] ?? ''),
            'access_code' => (string) ($response['data']['access_code'] ?? ''),
            'reference' => (string) ($response['data']['reference'] ?? $payload['reference']),
        ];
    }

    /**
     * Verify a returned transaction. Callback pages MUST call this
     * before crediting anything to the user — the Paystack redirect
     * itself carries only a reference, which is trivial to forge.
     *
     * @return array<string, mixed> the full `data` object from Paystack,
     *                              including `status`, `customer`, `authorization`, `plan_object`, `paid_at`
     */
    public function verifyTransaction(string $reference): array
    {
        $response = $this->request()
            ->get($this->baseUrl.'/transaction/verify/'.urlencode($reference))
            ->throw()
            ->json();

        $this->ensureOk($response, 'verifyTransaction');

        return (array) ($response['data'] ?? []);
    }

    /**
     * Fetch a subscription by SUB_ code. Used by the "am I still
     * subscribed" reconcile job and by staff when investigating a
     * customer's state.
     *
     * @return array<string, mixed>
     */
    public function fetchSubscription(string $subscriptionCode): array
    {
        $response = $this->request()
            ->get($this->baseUrl.'/subscription/'.urlencode($subscriptionCode))
            ->throw()
            ->json();

        $this->ensureOk($response, 'fetchSubscription');

        return (array) ($response['data'] ?? []);
    }

    /**
     * Cancel a subscription. Paystack requires the subscription's
     * one-time "email token" as well as the SUB_ code — fetch it from
     * `fetchSubscription()['email_token']` when the caller does not
     * already have it.
     *
     * Subscription stays active until the next billing date does not
     * fire; the entitlement window on our side (plan_expires_at) is
     * unchanged by this call.
     */
    public function disableSubscription(string $subscriptionCode, string $emailToken): void
    {
        $response = $this->request()
            ->post($this->baseUrl.'/subscription/disable', [
                'code' => $subscriptionCode,
                'token' => $emailToken,
            ])
            ->throw()
            ->json();

        $this->ensureOk($response, 'disableSubscription');
    }

    /**
     * Create (or return an existing matching) plan. Only used by the
     * one-shot `paystack:setup-plans` artisan command; the runtime
     * upgrade flow references pre-existing plan codes from config.
     *
     * @param  'monthly'|'annually'  $interval
     * @return array{plan_code:string, name:string, interval:string, amount:int, created:bool}
     */
    public function createPlan(string $name, string $interval, int $amountCents): array
    {
        // Prefer the "list plans" endpoint to spot an existing plan
        // with the same name — creating a duplicate is legal on
        // Paystack's side but pollutes the dashboard.
        $existing = $this->request()
            ->get($this->baseUrl.'/plan', ['perPage' => 100])
            ->throw()
            ->json('data', []);

        foreach ((array) $existing as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            if (($plan['name'] ?? null) === $name && ($plan['interval'] ?? null) === $interval) {
                return [
                    'plan_code' => (string) ($plan['plan_code'] ?? ''),
                    'name' => (string) ($plan['name'] ?? $name),
                    'interval' => (string) ($plan['interval'] ?? $interval),
                    'amount' => (int) ($plan['amount'] ?? $amountCents),
                    'created' => false,
                ];
            }
        }

        $response = $this->request()
            ->post($this->baseUrl.'/plan', [
                'name' => $name,
                'interval' => $interval,
                'amount' => $amountCents,
                'currency' => config('services.paystack.currency', 'ZAR'),
            ])
            ->throw()
            ->json();

        $this->ensureOk($response, 'createPlan');

        return [
            'plan_code' => (string) ($response['data']['plan_code'] ?? ''),
            'name' => (string) ($response['data']['name'] ?? $name),
            'interval' => (string) ($response['data']['interval'] ?? $interval),
            'amount' => (int) ($response['data']['amount'] ?? $amountCents),
            'created' => true,
        ];
    }

    /**
     * Verify the HMAC-SHA512 signature Paystack attaches to every
     * webhook. MUST be run against the raw request body — any
     * re-serialisation of the JSON produces a mismatch.
     */
    public static function verifyWebhookSignature(string $rawBody, string $signature, string $secretKey): bool
    {
        $expected = hash_hmac('sha512', $rawBody, $secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Paystack signs webhooks with the secret key. A dedicated webhook
     * secret overrides that when one has been configured.
     */
    public static function webhookSigningSecret(): string
    {
        $dedicated = (string) config('services.paystack.webhook_secret');

        return $dedicated !== '' ? $dedicated : (string) config('services.paystack.secret_key');
    }

    /**
     * ZAR banks for the payout form. Banks missing from Paystack's
     * verification list are returned with enabled_for_verification false
     * so the form can warn instead of blocking.
     *
     * Two list calls, capped at 10 pages each. Cache this on the payout
     * page (the bank list changes rarely) before it sits on a request path.
     *
     * @return list<array{name: string, code: string, enabled_for_verification: bool, supported_types: list<string>}>
     */
    public function listBanks(?string $currency = null): array
    {
        $currency = $currency ?: (string) config('services.paystack.currency', 'ZAR');

        $all = $this->fetchBankPages(['currency' => $currency]);
        $verifiable = $this->fetchBankPages([
            'currency' => $currency,
            'enabled_for_verification' => 'true',
        ]);

        $verifiableByCode = [];

        foreach ($verifiable as $bank) {
            $code = (string) ($bank['code'] ?? '');

            if ($code !== '') {
                $verifiableByCode[$code] = $bank;
            }
        }

        $banks = [];

        foreach ($all as $bank) {
            $code = (string) ($bank['code'] ?? '');
            $name = (string) ($bank['name'] ?? '');

            if ($code === '' || $name === '') {
                continue;
            }

            $match = $verifiableByCode[$code] ?? null;
            $supported = is_array($match) ? ($match['supported_types'] ?? []) : [];

            $banks[] = [
                'name' => $name,
                'code' => $code,
                'enabled_for_verification' => is_array($match),
                'supported_types' => array_values(array_filter(
                    is_array($supported) ? $supported : [],
                    fn (mixed $type): bool => is_string($type) && $type !== '',
                )),
            ];
        }

        usort($banks, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $banks;
    }

    /**
     * Confirm a South African account before a subaccount is created.
     * A failed check is a result, not an exception: the caller shows
     * `message` and lets the club correct the details.
     *
     * @return array{verified: bool, message: string, checks: array<string, bool>}
     */
    public function validateBankAccount(
        string $bankCode,
        #[\SensitiveParameter]
        string $accountNumber,
        string $accountName,
        string $accountType,
        string $documentType,
        #[\SensitiveParameter]
        string $documentNumber,
        string $countryCode = 'ZA',
    ): array {
        $response = $this->request()->post($this->baseUrl.'/bank/validate', [
            'bank_code' => $bankCode,
            'country_code' => $countryCode,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'account_type' => $accountType,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
        ]);

        if ($response->serverError() || $response->status() === 429) {
            $response->throw();
        }

        $body = $response->json();

        if (! is_array($body)) {
            $response->throw();
        }

        $checks = [];

        foreach ([
            'accountAcceptsDebits',
            'accountAcceptsCredits',
            'accountHolderMatch',
            'accountOpenForMoreThanThreeMonths',
            'accountOpen',
        ] as $key) {
            $value = data_get($body, 'data.'.$key);

            if (is_bool($value)) {
                $checks[$key] = $value;
            }
        }

        return [
            'verified' => ($body['status'] ?? false) === true && (bool) data_get($body, 'data.verified', false),
            'message' => (string) (data_get($body, 'data.verificationMessage') ?: ($body['message'] ?? 'Bank validation failed.')),
            'checks' => $checks,
        ];
    }

    /**
     * Create a settlement subaccount. percentage_charge is always 0:
     * the platform's cut is the flat transaction_charge on each
     * payment, not a percentage of the subaccount. Call
     * {@see updateSubaccount()} when the club already has an ACCT_ code.
     *
     * @return array{subaccount_code: string, is_verified: bool, active: bool}
     */
    public function createSubaccount(
        string $businessName,
        string $bankCode,
        #[\SensitiveParameter]
        string $accountNumber,
        ?string $contactEmail = null,
        ?string $contactName = null,
    ): array {
        $response = $this->request()
            ->post($this->baseUrl.'/subaccount', $this->subaccountPayload(
                $businessName,
                $bankCode,
                $accountNumber,
                $contactEmail,
                $contactName,
                creating: true,
            ))
            ->throw()
            ->json();

        $this->ensureOk($response, 'createSubaccount');

        return $this->subaccountResult($response, 'createSubaccount');
    }

    /**
     * @return array{subaccount_code: string, is_verified: bool, active: bool}
     */
    public function updateSubaccount(
        string $subaccountCode,
        string $businessName,
        string $bankCode,
        #[\SensitiveParameter]
        string $accountNumber,
        ?string $contactEmail = null,
        ?string $contactName = null,
    ): array {
        if ($subaccountCode === '') {
            throw new InvalidArgumentException('A Paystack subaccount code is required.');
        }

        $response = $this->request()
            ->put($this->baseUrl.'/subaccount/'.urlencode($subaccountCode), $this->subaccountPayload(
                $businessName,
                $bankCode,
                $accountNumber,
                $contactEmail,
                $contactName,
                creating: false,
            ))
            ->throw()
            ->json();

        $this->ensureOk($response, 'updateSubaccount');

        return $this->subaccountResult($response, 'updateSubaccount');
    }

    /**
     * Update an existing ACCT_ record. Creates one only when the club
     * does not already have a code, so a second settlement record is
     * never opened for the same club.
     *
     * @return array{subaccount_code: string, is_verified: bool, active: bool}
     */
    public function syncSubaccount(
        ?string $existingCode,
        string $businessName,
        string $bankCode,
        #[\SensitiveParameter]
        string $accountNumber,
        ?string $contactEmail = null,
        ?string $contactName = null,
    ): array {
        if (filled($existingCode)) {
            return $this->updateSubaccount($existingCode, $businessName, $bankCode, $accountNumber, $contactEmail, $contactName);
        }

        return $this->createSubaccount($businessName, $bankCode, $accountNumber, $contactEmail, $contactName);
    }

    /**
     * One-off match fee split to a single club subaccount.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: string, access_code: string, reference: string}
     */
    public function initializeSplitTransaction(
        string $email,
        int $amountCents,
        string $subaccountCode,
        int $transactionChargeCents,
        string $bearer,
        string $callbackUrl,
        ?string $reference = null,
        array $metadata = [],
    ): array {
        if ($subaccountCode === '') {
            throw new InvalidArgumentException('A Paystack subaccount code is required.');
        }

        if (! in_array($bearer, [PaystackFeeBearer::Account->value, PaystackFeeBearer::Subaccount->value], true)) {
            throw new InvalidArgumentException('Bearer must be account (platform) or subaccount (club).');
        }

        if ($amountCents < 1 || $transactionChargeCents < 0 || $transactionChargeCents >= $amountCents) {
            throw new InvalidArgumentException('The platform charge must be less than the amount collected.');
        }

        $payload = [
            'email' => $email,
            'amount' => $amountCents,
            'currency' => config('services.paystack.currency', 'ZAR'),
            'callback_url' => $callbackUrl,
            'reference' => $reference ?? $this->generateReference(),
            'metadata' => $metadata,
            'subaccount' => $subaccountCode,
            'transaction_charge' => $transactionChargeCents,
            'bearer' => $bearer,
        ];

        $response = $this->request()
            ->post($this->baseUrl.'/transaction/initialize', $payload)
            ->throw()
            ->json();

        $this->ensureOk($response, 'initializeSplitTransaction');

        return [
            'authorization_url' => (string) ($response['data']['authorization_url'] ?? ''),
            'access_code' => (string) ($response['data']['access_code'] ?? ''),
            'reference' => (string) ($response['data']['reference'] ?? $payload['reference']),
        ];
    }

    /**
     * Full refund when $amountCents is null. Partial when it is set.
     * The reason is required and sent as both merchant and customer notes.
     *
     * @return array<string, mixed>
     */
    public function createRefund(string $transactionReference, string $reason, ?int $amountCents = null): array
    {
        if ($transactionReference === '') {
            throw new InvalidArgumentException('A transaction reference is required.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A refund reason is required.');
        }

        if ($amountCents !== null && $amountCents < 1) {
            throw new InvalidArgumentException('A partial refund amount must be at least 1 cent.');
        }

        $payload = [
            'transaction' => $transactionReference,
            'currency' => config('services.paystack.currency', 'ZAR'),
            'merchant_note' => $reason,
            'customer_note' => $reason,
        ];

        if ($amountCents !== null) {
            $payload['amount'] = $amountCents;
        }

        $response = $this->request()
            ->post($this->baseUrl.'/refund', $payload)
            ->throw()
            ->json();

        $this->ensureOk($response, 'createRefund');

        return (array) ($response['data'] ?? []);
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function ensureOk(array $response, string $op): void
    {
        $status = $response['status'] ?? false;

        if ($status === true) {
            return;
        }

        $message = (string) ($response['message'] ?? 'Unknown Paystack error');

        throw new RuntimeException("Paystack {$op} failed: {$message}");
    }

    private function generateReference(): string
    {
        // Prefix so it's obvious in the Paystack dashboard which
        // integration a transaction came from. Underscore-safe.
        return 'ss_'.now()->format('YmdHis').'_'.Str::lower(Str::random(10));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function fetchBankPages(array $query): array
    {
        $page = 1;
        $banks = [];

        do {
            $payload = $this->request()
                ->get($this->baseUrl.'/bank', array_merge($query, [
                    'perPage' => 100,
                    'page' => $page,
                ]))
                ->throw()
                ->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Paystack listBanks failed: empty response');
            }

            $this->ensureOk($payload, 'listBanks');

            foreach ((array) ($payload['data'] ?? []) as $bank) {
                if (is_array($bank)) {
                    $banks[] = $bank;
                }
            }

            $pageCount = max(1, (int) data_get($payload, 'meta.pageCount', 1));
            $page++;
        } while ($page <= $pageCount && $page <= 10);

        return $banks;
    }

    /**
     * @return array<string, mixed>
     */
    private function subaccountPayload(
        string $businessName,
        string $bankCode,
        string $accountNumber,
        ?string $contactEmail,
        ?string $contactName,
        bool $creating,
    ): array {
        $payload = [
            'business_name' => $businessName,
            'account_number' => $accountNumber,
            // 0 so the main account takes nothing unless a transaction
            // passes an explicit flat transaction_charge.
            'percentage_charge' => 0,
        ];

        $payload[$creating ? 'settlement_bank' : 'bank_code'] = $bankCode;

        if ($contactEmail !== null && $contactEmail !== '') {
            $payload['primary_contact_email'] = $contactEmail;
        }

        if ($contactName !== null && $contactName !== '') {
            $payload['primary_contact_name'] = $contactName;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{subaccount_code: string, is_verified: bool, active: bool}
     */
    private function subaccountResult(array $response, string $op): array
    {
        $code = (string) ($response['data']['subaccount_code'] ?? '');

        if ($code === '') {
            throw new RuntimeException("Paystack {$op} failed: missing subaccount code");
        }

        return [
            'subaccount_code' => $code,
            'is_verified' => (bool) ($response['data']['is_verified'] ?? false),
            'active' => (bool) ($response['data']['active'] ?? false),
        ];
    }
}
