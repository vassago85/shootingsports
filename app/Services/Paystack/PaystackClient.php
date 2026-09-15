<?php

namespace App\Services\Paystack;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
}
