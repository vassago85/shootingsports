# Paystack setup (server)

The app runs Paystack subscriptions with two plans:

- **Pro (Annual)** — R299 / year
- **Pro (Monthly)** — R29 / month

Both plans auto-renew. Users pick one at `/upgrade`, get sent to
Paystack Checkout, and land on `/paystack/callback` on success.
Renewals, cancellations, and failed cards arrive as webhooks at
`/paystack/webhook`.

Everything is wired via config — nothing hardcoded — so switching from
test to live is an `.env` swap plus a plan-code refresh.

---

## 1. First-time setup

**On the Paystack side** (dashboard.paystack.com):

1. Business verification: bank account, BEE, ID docs — Paystack blocks
   live keys until this is done.
2. Copy the **test** keys from Settings → API Keys & Webhooks:
   - Test Public Key (`pk_test_...`)
   - Test Secret Key (`sk_test_...`)
3. Add the webhook URL to the same page:
   - Test webhook URL: `https://shootingsports.co.za/paystack/webhook`
   - (Once live: same URL — Paystack sends test vs live from separate keys.)

**On the server** (`/opt/shootingsports/.env`):

```env
PAYSTACK_PUBLIC_KEY=pk_test_XXXXXXXXXXXX
PAYSTACK_SECRET_KEY=sk_test_XXXXXXXXXXXX
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CURRENCY=ZAR
# Filled in step 2 below:
PAYSTACK_PLAN_ANNUAL=
PAYSTACK_PLAN_MONTHLY=
```

## 2. Create the two plans

Run the idempotent setup command inside the app container:

```bash
docker exec shootingsports-app php artisan paystack:setup-plans
```

It will:

- List existing plans on the connected Paystack account.
- Reuse any plan whose name and interval already matches (so re-runs
  don't spawn duplicates).
- Create the missing plans with the amounts defined in
  `config/plans.php` under `pricing.annual.amount_cents` and
  `pricing.monthly.amount_cents`.
- Print the `PLN_...` codes to paste into `.env`.

Paste the two codes into `.env` and refresh config:

```bash
docker exec shootingsports-app php artisan config:cache
docker compose restart app
```

## 3. Smoke-test with a test card

Paystack's shared test cards live at
https://paystack.com/docs/payments/test-payments. A dependable one:

- Card number: `4084 0840 8408 4081`
- Expiry: any future date
- CVV: `408`
- PIN / OTP: `1234` / `123456`

Flow:

1. Sign in as a Free user.
2. Go to `/upgrade`, pick a plan, hit "Continue to secure checkout".
3. Complete the Paystack test payment.
4. You land on `/my-calendar` with the "Welcome to Pro" flash.
5. Confirm in the Filament admin (`/admin`) → Users → your record →
   the subscription section shows the SUB_ code, next billing date,
   and cycle.

## 4. Going live

- Complete Paystack business verification.
- Swap `PAYSTACK_PUBLIC_KEY` and `PAYSTACK_SECRET_KEY` in `.env` for
  the **live** keys (`pk_live_...`, `sk_live_...`).
- Re-run `php artisan paystack:setup-plans` — this creates fresh
  plans in the live account, whose codes will differ from the test
  ones. Paste the new codes into `.env`.
- `php artisan config:cache` and restart the app container.
- Confirm the webhook URL is set in the live-mode section of the
  Paystack dashboard (Settings → API Keys & Webhooks → Live tab).

## 5. Ops runbook

**A customer paid but their account isn't Pro**

1. Grab the reference from Paystack dashboard → Transactions.
2. Look for the row in `paystack_events` on the DB:
   ```bash
   docker exec shootingsports-app php artisan tinker --execute="
       \App\Models\PaystackEvent::where('reference', 'ref_xxxx')->first();
   "
   ```
3. If `processed_at` is `null` and there's an `error` — that's the
   handler crash. Fix the bug then replay by clearing `processed_at`
   and re-POSTing the payload (or re-trigger from Paystack dashboard).
4. If there's no row at all — the webhook never reached us. Check
   Paystack dashboard → Webhook logs for retries and delivery status.
5. As a last resort, use the "Grant Pro comp" action on the User
   record in Filament to manually credit them for the amount they
   were charged.

**Cancel a subscription for a user**

- Preferred: the user does it themselves from `/upgrade`.
- Staff: from Filament → Users → open the record → check
  `paystack_subscription_code`, then cancel it from the Paystack
  dashboard (Customers → find → Subscriptions → Cancel). The
  `subscription.disable` webhook will mark them as cancelling on
  our side automatically.

**Comp Pro for a user**

- Filament → Users → open the record → "Grant Pro comp" action →
  enter months.
- This does NOT touch Paystack — no auto-renewal, no card charge.
  When the granted period runs out the user drops back to Free
  automatically (via `HasPlan::isPro()`).

## 6. Fields, at a glance

Everything about a user's subscription state lives on `users`:

| Column                          | Meaning                                                                 |
|---------------------------------|-------------------------------------------------------------------------|
| `plan`                          | `free` \| `pro` — nominal plan (see `App\Enums\Plan`).                  |
| `plan_expires_at`               | Single source of truth for "when Pro runs out". Null = never.            |
| `plan_billing_cycle`            | `annual` \| `monthly` \| null (null = comp / manual).                    |
| `plan_cancelled_at`             | Set when the user cancels; entitlement window stays intact until expiry. |
| `paystack_customer_code`        | CUS_... — set on first charge.                                          |
| `paystack_subscription_code`    | SUB_... — the active auto-renewing subscription.                        |
| `paystack_authorization_code`   | AUTH_... — tokenised card, so we can spin up a new sub without a fresh charge. |

Webhook audit log: `paystack_events` (PK = the Paystack event id, so
retries are naturally deduped).
