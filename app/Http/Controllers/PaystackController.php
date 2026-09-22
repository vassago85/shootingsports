<?php

namespace App\Http\Controllers;

use App\Models\PaystackEvent;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\SubscriptionApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two entry points for Paystack:
 *
 *   GET  /paystack/callback  — browser return after Checkout. Verifies
 *                              the transaction, upgrades the user, and
 *                              redirects with a flash message.
 *
 *   POST /paystack/webhook   — server-to-server delivery for every
 *                              subsequent event (renewals, cancels,
 *                              failed cards). Signature-verified,
 *                              deduped, then dispatched to handlers.
 *
 * Neither route is behind auth — Paystack Checkout may return the
 * customer on a different session and the webhook comes from
 * Paystack's IPs, not the customer. The user is identified by the
 * metadata / customer email in the payload.
 */
class PaystackController extends Controller
{
    public function __construct(
        private readonly PaystackClient $client,
        private readonly SubscriptionApplier $applier,
    ) {}

    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        if ($reference === '') {
            return redirect('/upgrade')->with('status', 'Missing transaction reference. Nothing was charged.');
        }

        try {
            $verified = $this->client->verifyTransaction($reference);
        } catch (\Throwable $e) {
            report($e);

            return redirect('/upgrade')->with('status', 'Could not verify that transaction. If your card was charged, contact us and we will fix it manually.');
        }

        if (($verified['status'] ?? '') !== 'success') {
            return redirect('/upgrade')->with('status', 'Payment was not completed. Nothing was charged.');
        }

        $user = $this->resolveUserFromVerified($verified);

        if (! $user instanceof User) {
            Log::warning('Paystack callback: could not resolve user for reference', [
                'reference' => $reference,
                'customer_email' => data_get($verified, 'customer.email'),
                'metadata' => data_get($verified, 'metadata'),
            ]);

            return redirect('/upgrade')->with('status', 'We could not match that payment to your account. If your card was charged, contact us with the reference and we will sort it out.');
        }

        $cycle = $this->cycleFromMetadata($verified) ?? 'annual';

        $this->applier->applyInitialSubscription($user, $reference, $verified, $cycle);

        // Log the user in if they're not already (Paystack Checkout can
        // land the customer on a fresh browser session, e.g. after
        // completing on their phone).
        if (auth()->guest()) {
            auth()->login($user);
        }

        return redirect('/my-calendar')->with('status', 'Welcome to Pro. Renewal happens automatically at the end of each billing cycle.');
    }

    /**
     * User-triggered cancel from the /upgrade page. Calls Paystack to
     * stop future charges, marks the local user as cancelling, and
     * leaves plan_expires_at alone — the customer keeps Pro until the
     * end of the billing period they've already paid for.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->hasActiveSubscription()) {
            return redirect('/upgrade')->with('status', 'No active subscription to cancel.');
        }

        try {
            // Cancelling a Paystack subscription requires the SUB_ code
            // AND a one-time email_token that we have to fetch fresh —
            // Paystack does not expose it on the subscription list.
            $sub = $this->client->fetchSubscription($user->paystack_subscription_code);
            $token = (string) ($sub['email_token'] ?? '');

            if ($token === '') {
                throw new \RuntimeException('Paystack did not return an email_token for the subscription.');
            }

            $this->client->disableSubscription($user->paystack_subscription_code, $token);
        } catch (\Throwable $e) {
            report($e);

            return redirect('/upgrade')->with('status', 'Could not cancel automatically. Email us and we will do it manually. No further charges will happen.');
        }

        $this->applier->applyCancellation($user);

        return redirect('/upgrade')->with('status', 'Cancelled. Pro stays active until '.$user->fresh()->plan_expires_at?->format('j M Y').'.');
    }

    public function webhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');
        $secretKey = (string) config('services.paystack.secret_key');

        if ($secretKey === '' || $signature === '' || ! PaystackClient::verifyWebhookSignature($rawBody, $signature, $secretKey)) {
            // Return 200 anyway per Paystack docs — a 401 causes their
            // retry queue to keep hammering the endpoint. Log for audit
            // then move on.
            Log::warning('Paystack webhook rejected (bad signature or missing secret)', [
                'signature_present' => $signature !== '',
                'secret_present' => $secretKey !== '',
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'invalid'], 200);
        }

        $payload = json_decode($rawBody, true) ?: [];
        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['event'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response()->json(['status' => 'malformed'], 200);
        }

        // Idempotency via unique PK on paystack_events.id. If we've
        // already logged this exact event we short-circuit — Paystack
        // retries the same event multiple times on any 5xx or timeout.
        $event = PaystackEvent::query()->find($eventId);

        if ($event !== null && $event->processed_at !== null) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        if ($event === null) {
            $event = PaystackEvent::create([
                'id' => $eventId,
                'event_type' => $eventType,
                'reference' => (string) data_get($payload, 'data.reference', '') ?: null,
                'payload' => $payload,
            ]);
        }

        try {
            DB::transaction(function () use ($event, $eventType, $payload): void {
                $this->dispatch($eventType, (array) ($payload['data'] ?? []));
                $event->forceFill(['processed_at' => now(), 'error' => null])->save();
            });
        } catch (\Throwable $e) {
            report($e);
            $event->forceFill(['error' => $e->getMessage()])->save();

            // 500 makes Paystack retry — we want that when our own
            // handler bombed. Only signature / dedup skips return 200.
            return response()->json(['status' => 'handler_failed'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $eventType, array $data): void
    {
        match ($eventType) {
            'charge.success' => $this->onChargeSuccess($data),
            'subscription.create' => $this->onSubscriptionCreate($data),
            'subscription.disable', 'subscription.not_renew' => $this->onSubscriptionCancelled($data),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($data),
            default => null, // Unknown events are recorded but not processed. Fine.
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onChargeSuccess(array $data): void
    {
        $email = (string) data_get($data, 'customer.email', '');
        $subscriptionCode = (string) data_get($data, 'plan_object.subscription_code', '');

        $user = $this->resolveUser($email, $subscriptionCode);

        if (! $user instanceof User) {
            return;
        }

        $planInterval = (string) data_get($data, 'plan.interval', '');
        $cycle = $planInterval === 'annually' ? 'annual' : 'monthly';

        // First-charge and renewal look almost identical in the payload
        // — we distinguish by whether we've already recorded this user
        // as subscribed. First time: applyInitialSubscription (sets the
        // authorisation + customer code). Subsequent: applyRenewal.
        if ($user->paystack_subscription_code === null || $user->paystack_customer_code === null) {
            $this->applier->applyInitialSubscription(
                user: $user,
                reference: (string) ($data['reference'] ?? ''),
                verified: $data,
                cycle: $cycle,
            );
        } else {
            $this->applier->applyRenewal($user, $cycle);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onSubscriptionCreate(array $data): void
    {
        $email = (string) data_get($data, 'customer.email', '');
        $subscriptionCode = (string) data_get($data, 'subscription_code', '');
        $customerCode = (string) data_get($data, 'customer.customer_code', '');

        $user = $this->resolveUser($email, $subscriptionCode);

        if (! $user instanceof User) {
            return;
        }

        // Fill in the codes if the callback missed them (Paystack
        // sometimes fires subscription.create before charge.success
        // is fully persisted on their side).
        $user->forceFill([
            'paystack_subscription_code' => $subscriptionCode !== '' ? $subscriptionCode : $user->paystack_subscription_code,
            'paystack_customer_code' => $customerCode !== '' ? $customerCode : $user->paystack_customer_code,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onSubscriptionCancelled(array $data): void
    {
        $email = (string) data_get($data, 'customer.email', '');
        $subscriptionCode = (string) data_get($data, 'subscription_code', '');

        $user = $this->resolveUser($email, $subscriptionCode);

        if (! $user instanceof User) {
            return;
        }

        $this->applier->applyCancellation($user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function onInvoicePaymentFailed(array $data): void
    {
        // Grace-period behaviour is minimal for v1: log the event,
        // leave the user on Pro until plan_expires_at rolls past.
        // Paystack retries failed charges automatically for ~72 hours.
        Log::info('Paystack invoice payment failed', [
            'reference' => data_get($data, 'reference'),
            'customer_email' => data_get($data, 'customer.email'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $verified
     */
    private function resolveUserFromVerified(array $verified): ?User
    {
        $metadataUserId = (int) data_get($verified, 'metadata.user_id', 0);
        if ($metadataUserId > 0) {
            $user = User::query()->find($metadataUserId);
            if ($user instanceof User) {
                return $user;
            }
        }

        $email = (string) data_get($verified, 'customer.email', '');

        return $this->resolveUser($email, '');
    }

    private function resolveUser(string $email, string $subscriptionCode): ?User
    {
        if ($subscriptionCode !== '') {
            $bySub = User::query()->where('paystack_subscription_code', $subscriptionCode)->first();
            if ($bySub instanceof User) {
                return $bySub;
            }
        }

        if ($email !== '') {
            $byEmail = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if ($byEmail instanceof User) {
                return $byEmail;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $verified
     */
    private function cycleFromMetadata(array $verified): ?string
    {
        $cycle = (string) data_get($verified, 'metadata.cycle', '');
        if ($cycle === 'annual' || $cycle === 'monthly') {
            return $cycle;
        }

        // Fall back to the plan interval on the verified transaction —
        // Paystack returns 'monthly' or 'annually' on the plan object.
        $interval = (string) data_get($verified, 'plan.interval', '');

        return $interval === 'annually' ? 'annual' : ($interval === 'monthly' ? 'monthly' : null);
    }
}
