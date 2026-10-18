# Platform Billing & Payment Gateway Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship platform subscription billing (R100/R500/R800 tiers + annual) plus a Section 54 agency-compliant Paystack gateway integration that takes a 2% + VAT platform fee on every member payment, with a bank-transfer settlement fallback.

**Architecture:** Extends the existing `MembershipInvoice` ledger from the Club Membership Manager spec. New models for `PlatformSubscription`, `PlatformInvoice`, `PlatformFeeStatement`, `ClubPayable`, and `PlatformPayout`. Reuses the existing `PaystackController` webhook branch pattern (`purpose` metadata key). All platform-fee math flows through a single `PlatformFees` helper so VAT and basis-point calculations happen in one place.

**Tech Stack:** Laravel 13 + Filament 5.8 + Livewire 4 + Pest 5 + Playwright 1.63 + Postgres (prod) / SQLite (test). Paystack Transactions API (one-off, recurring, subaccounts). Blade for emails and tax invoices.

**Spec:** `docs/superpowers/specs/2026-10-18-platform-billing-and-payment-gateway-design.md`

## Plan amendments (post-pre-flight, 2026-10-18)

These supersede the plan text wherever they conflict. Each is also noted inline on the affected task.

1. **Task 5 — Settings page pattern.** This project uses a `App\Filament\Pages\Settings` landing page that lists child pages (not tabs). Each subject is a `Manage<Subject>Settings` page backed by an `App\Support\<Subject>Settings` support class whose `apply()` is called from `AppServiceProvider::boot()`. Follow this pattern. **Do not** add an inline `applyPlatformSettings()` method on the service provider.
2. **Tasks 6 + 7 — Paystack recurring architecture.** This project already uses Paystack-managed Plans + Subscriptions (`App\Services\Paystack\PaystackClient::createPlan`, `fetchSubscription`, `disableSubscription`, and `initializeTransaction` with a `plan` parameter). Platform billing aligns with that: provision 6 plans upfront (`PaystackPlatformPlanService`), kick off subscriptions with `initializeTransaction(..., plan: $code)`, listen for lifecycle webhooks (`PlatformBillingWebhookHandler`) to drive status transitions. **Do not** implement `charge_authorization` or a daily `BillRecurringSubscriptions` command — Paystack bills on its own cadence.
3. **Task 17 — `organisations.kind`.** Column confirmed absent as of 2026-10-18. Add it in a new migration (string, default `'club'`, backfill existing rows) and introduce an `App\Enums\OrganisationKind` enum.
4. **Settings path references.** Everywhere this plan says `App\Filament\Pages\SiteSettings`, the actual file is `App\Filament\Pages\Settings`.
5. **"Mailgun pattern" reference.** Point of truth is `App\Support\MailSettings` + `App\Filament\Pages\ManageMailSettings`. Mirror these exactly for the Platform billing settings page — not the SAPRF `applyMailgunSettings()` provider method mentioned in prior conversations.

## Global Constraints

- **PHP 8.3+** — curly braces always, PHP 8 constructor property promotion, explicit return types, PHPDoc blocks over inline comments.
- **Follow existing patterns** — mirror `ClubMemberResource` for new Desk resources, `MembershipInvoicesTable` row-action schema style, `PaystackController::onChargeSuccess` branching for new webhook branches.
- **Pint on dirty PHP** — `vendor/bin/pint --dirty --format agent` after every PHP touch, before every commit.
- **Pest first** — RED-GREEN-REFACTOR. Write the failing test, verify it fails, implement minimally, verify it passes, commit.
- **No new dependencies without approval** — build on what's installed.
- **Money is cents** — all amounts stored as `int` columns suffixed `_cents`. VAT rate stored as basis points (`vat_bps`, default `1500`). Platform fee rate stored as basis points (`fee_bps`, default `200`).
- **VAT identities:** `fee_cents = round(gross_cents * fee_bps / 10000)` and `fee_vat_cents = round(fee_cents * vat_bps / 10000)`. These two formulas live in `App\Support\PlatformFees` and nowhere else.
- **Agency framing is load-bearing** — see Section 54 compliance section in the spec. Three TBD-pending-accountant placeholders in Blade copy are allowed; everything else must be real content.
- **Settings table** — all configurable values stored in the `settings` table, applied via `AppServiceProvider` like the existing Mailgun pattern.
- **Site-wide context** — this is the `shootingsports` project (Postgres on prod `/opt/shootingsports`, Laragon + SQLite locally). Container names `shootingsports-*`.

---

### Task 0: Feasibility probe (no-code deliverable)

**Files:**
- Create: `docs/superpowers/notes/2026-10-18-paystack-subaccount-feasibility.md`

**Interfaces:** None. Produces a written go/no-go for the subaccount path.

- [ ] **Step 1: Document the three questions the probe answers**

```markdown
# Paystack SA Subaccount Feasibility — 2026-10-18

## Questions

1. Does Paystack SA support subaccount creation for SA merchants in 2026?
2. Does Paystack SA support `transaction_charge` alongside `subaccount` on `initializeTransaction`?
3. What KYC does a subaccount require, and is it workable for a volunteer club secretary?

## Evidence
TODO: fill after dev-account probe.

## Decision
TODO: PRIMARY=subaccounts or PRIMARY=bank_transfer
```

- [ ] **Step 2: Hit Paystack-SA docs + support channel**

Read current Paystack docs for Subaccounts, Multi-split, and the Transactions API. Note any SA-specific flags. If ambiguous, email Paystack SA support with the three questions above and attach their reply.

- [ ] **Step 3: Dev-account probe**

Create a Paystack test account for shootingsports (if not already). Attempt:

```bash
curl -X POST https://api.paystack.co/subaccount \
  -H "Authorization: Bearer <sk_test_xxx>" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Test Club",
    "settlement_bank": "058",
    "account_number": "0123456789",
    "percentage_charge": 2.3
  }'
```

Expected: `201 Created` with a `subaccount_code`. Then initialise a test transaction with that subaccount + a `transaction_charge` and confirm the split works on a R10 test charge.

- [ ] **Step 4: Fill in the decision**

Record the answers. If any question is "no", set `PRIMARY=bank_transfer` in the notes file. The plan still ships both paths — this decision just reorders which one Phase 2 wires as the default `payout_method`.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/notes/2026-10-18-paystack-subaccount-feasibility.md
git commit -m "Paystack SA subaccount feasibility probe results"
```

---

### Task 1: `PlatformFees` helper + VAT identities

**Files:**
- Create: `app/Support/PlatformFees.php`
- Create: `tests/Unit/PlatformFeesTest.php`

**Interfaces:**
- Produces:
  - `PlatformFees::feeCents(int $grossCents): int`
  - `PlatformFees::feeVatCents(int $feeCents): int`
  - `PlatformFees::vatInclusiveCents(int $exclusiveCents): int`
  - `PlatformFees::splitFor(int $grossCents): array{gross:int, fee:int, fee_vat:int, net_to_club_estimate:int}`
  - `PlatformFees::feeBps(): int`
  - `PlatformFees::vatBps(): int`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\PlatformFees;

it('computes 2 percent fee on R600 as R12', function () {
    config()->set('platform.fee_bps', 200);

    expect(PlatformFees::feeCents(60000))->toBe(1200);
});

it('computes 15 percent VAT on R12 fee as R1.80', function () {
    config()->set('platform.vat_bps', 1500);

    expect(PlatformFees::feeVatCents(1200))->toBe(180);
});

it('makes exclusive R100 into inclusive R115', function () {
    config()->set('platform.vat_bps', 1500);

    expect(PlatformFees::vatInclusiveCents(10000))->toBe(11500);
});

it('splits a R600 gross into the full breakdown', function () {
    config()->set('platform.fee_bps', 200);
    config()->set('platform.vat_bps', 1500);

    $split = PlatformFees::splitFor(60000);

    expect($split)->toMatchArray([
        'gross' => 60000,
        'fee' => 1200,
        'fee_vat' => 180,
        // net_to_club_estimate = gross - fee - fee_vat (Paystack fee unknown here)
        'net_to_club_estimate' => 58620,
    ]);
});
```

- [ ] **Step 2: Run and watch it fail**

Run: `php artisan test tests/Unit/PlatformFeesTest.php --compact`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the helper**

```php
<?php

namespace App\Support;

/**
 * Single source of truth for every platform-fee and VAT calculation
 * on the site. Keeps the Section 54 agency VAT identities in one place
 * so audits only have one method to inspect.
 */
class PlatformFees
{
    public static function feeBps(): int
    {
        return (int) config('platform.fee_bps', 200);
    }

    public static function vatBps(): int
    {
        return (int) config('platform.vat_bps', 1500);
    }

    public static function feeCents(int $grossCents): int
    {
        return (int) round($grossCents * self::feeBps() / 10000);
    }

    public static function feeVatCents(int $feeCents): int
    {
        return (int) round($feeCents * self::vatBps() / 10000);
    }

    public static function vatInclusiveCents(int $exclusiveCents): int
    {
        return (int) round($exclusiveCents * (10000 + self::vatBps()) / 10000);
    }

    /**
     * @return array{gross:int, fee:int, fee_vat:int, net_to_club_estimate:int}
     */
    public static function splitFor(int $grossCents): array
    {
        $fee = self::feeCents($grossCents);
        $vat = self::feeVatCents($fee);

        return [
            'gross' => $grossCents,
            'fee' => $fee,
            'fee_vat' => $vat,
            'net_to_club_estimate' => $grossCents - $fee - $vat,
        ];
    }
}
```

- [ ] **Step 4: Add config defaults**

Create `config/platform.php`:

```php
<?php

return [
    'fee_bps' => (int) env('PLATFORM_FEE_BPS', 200),
    'vat_bps' => (int) env('PLATFORM_VAT_BPS', 1500),
    'payout_admin_fee_cents' => (int) env('PLATFORM_PAYOUT_ADMIN_FEE_CENTS', 5000),
    'trial_days' => (int) env('PLATFORM_TRIAL_DAYS', 30),
    'grace_window_days' => (int) env('PLATFORM_GRACE_WINDOW_DAYS', 30),
    'dunning_retry_days' => [1, 4, 7, 14],
    'single_event_price_cents' => (int) env('PLATFORM_SINGLE_EVENT_PRICE_CENTS', 75000),
    'tier_prices_cents' => [
        'micro' => ['monthly' => 10000, 'annual' => 100000],
        'standard' => ['monthly' => 50000, 'annual' => 500000],
        'large' => ['monthly' => 80000, 'annual' => 800000],
    ],
    'tier_member_caps' => [
        'micro' => 50,
        'standard' => 250,
        'large' => 1000,
    ],
];
```

- [ ] **Step 5: Run tests, verify pass**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Unit/PlatformFeesTest.php --compact
```

Expected: 4 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Support/PlatformFees.php tests/Unit/PlatformFeesTest.php config/platform.php
git commit -m "Platform billing: PlatformFees helper with VAT and fee basis-point maths"
```

---

### Task 2: `SubscriptionTier` and `BillingInterval` enums + `PlatformSubscriptionStatus` enum

**Files:**
- Create: `app/Enums/SubscriptionTier.php`
- Create: `app/Enums/BillingInterval.php`
- Create: `app/Enums/PlatformSubscriptionStatus.php`
- Create: `tests/Unit/SubscriptionTierTest.php`

**Interfaces:**
- Produces: `SubscriptionTier::Micro|Standard|Large`, `BillingInterval::Monthly|Annual`, `PlatformSubscriptionStatus::Trial|TrialExpired|Active|PastDue|Suspended|Cancelled`.
- `SubscriptionTier::priceCents(BillingInterval): int` reads from `config('platform.tier_prices_cents')`.
- `SubscriptionTier::memberCap(): int` reads from `config('platform.tier_member_caps')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;

it('exposes prices from config for each tier and interval', function () {
    expect(SubscriptionTier::Micro->priceCents(BillingInterval::Monthly))->toBe(10000)
        ->and(SubscriptionTier::Standard->priceCents(BillingInterval::Annual))->toBe(500000)
        ->and(SubscriptionTier::Large->priceCents(BillingInterval::Monthly))->toBe(80000);
});

it('exposes active-member caps per tier', function () {
    expect(SubscriptionTier::Micro->memberCap())->toBe(50)
        ->and(SubscriptionTier::Standard->memberCap())->toBe(250)
        ->and(SubscriptionTier::Large->memberCap())->toBe(1000);
});
```

- [ ] **Step 2: Watch it fail**

```bash
php artisan test tests/Unit/SubscriptionTierTest.php --compact
```

- [ ] **Step 3: Implement the three enums**

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionTier: string implements HasLabel
{
    case Micro = 'micro';
    case Standard = 'standard';
    case Large = 'large';

    public function getLabel(): string
    {
        return match ($this) {
            self::Micro => 'Micro',
            self::Standard => 'Standard',
            self::Large => 'Large',
        };
    }

    public function priceCents(BillingInterval $interval): int
    {
        return (int) config("platform.tier_prices_cents.{$this->value}.{$interval->value}", 0);
    }

    public function memberCap(): int
    {
        return (int) config("platform.tier_member_caps.{$this->value}", 0);
    }

    public static function forMemberCount(int $count): self
    {
        return match (true) {
            $count <= self::Micro->memberCap() => self::Micro,
            $count <= self::Standard->memberCap() => self::Standard,
            default => self::Large,
        };
    }
}
```

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingInterval: string implements HasLabel
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Annual => 'Annual',
        };
    }
}
```

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlatformSubscriptionStatus: string implements HasLabel
{
    case Trial = 'trial';
    case TrialExpired = 'trial_expired';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::TrialExpired => 'Trial expired',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isReadOnly(): bool
    {
        return in_array($this, [self::TrialExpired, self::PastDue, self::Suspended], true);
    }
}
```

- [ ] **Step 4: Run tests, verify pass, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Unit/SubscriptionTierTest.php --compact
git add app/Enums/SubscriptionTier.php app/Enums/BillingInterval.php app/Enums/PlatformSubscriptionStatus.php tests/Unit/SubscriptionTierTest.php
git commit -m "Platform billing: SubscriptionTier, BillingInterval, PlatformSubscriptionStatus enums"
```

---

### Task 3: Migrations — organisations extensions + `platform_subscriptions`

**Files:**
- Create: `database/migrations/<timestamp>_add_platform_billing_columns_to_organisations_table.php`
- Create: `database/migrations/<timestamp>_create_platform_subscriptions_table.php`
- Create: `database/migrations/<timestamp>_add_platform_fee_columns_to_membership_invoices_table.php`

**Interfaces:**
- Produces:
  - `organisations.paystack_subaccount_id`, `organisations.paystack_subaccount_status`, `organisations.payout_method`, `organisations.agency_agreement_signed_at`, `organisations.agency_agreement_version`, `organisations.active_member_count`, `organisations.tier_grace_ends_at`
  - `platform_subscriptions` table
  - `membership_invoices.platform_fee_cents`, `platform_fee_vat_cents`, `gateway_fee_cents`, `net_to_club_cents`

- [ ] **Step 1: Generate the three migrations**

```bash
php artisan make:migration add_platform_billing_columns_to_organisations_table --table=organisations --no-interaction
php artisan make:migration create_platform_subscriptions_table --no-interaction
php artisan make:migration add_platform_fee_columns_to_membership_invoices_table --table=membership_invoices --no-interaction
```

- [ ] **Step 2: Fill in the organisations migration**

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('paystack_subaccount_id')->nullable()->unique();
            $table->string('paystack_subaccount_status')->nullable();
            $table->string('payout_method')->default('paystack_subaccount');
            $table->timestamp('agency_agreement_signed_at')->nullable();
            $table->string('agency_agreement_version')->nullable();
            $table->unsignedInteger('active_member_count')->default(0);
            $table->date('tier_grace_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn([
                'paystack_subaccount_id',
                'paystack_subaccount_status',
                'payout_method',
                'agency_agreement_signed_at',
                'agency_agreement_version',
                'active_member_count',
                'tier_grace_ends_at',
            ]);
        });
    }
};
```

- [ ] **Step 3: Fill in the platform_subscriptions migration**

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('tier');
            $table->string('interval');
            $table->string('status');
            $table->string('paystack_authorization_code')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedTinyInteger('dunning_retry_count')->default(0);
            $table->date('next_retry_on')->nullable();
            $table->timestamps();

            $table->unique('organisation_id');
            $table->index(['status', 'current_period_end']);
            $table->index(['status', 'next_retry_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscriptions');
    }
};
```

- [ ] **Step 4: Fill in the membership_invoices extension**

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_invoices', function (Blueprint $table): void {
            $table->unsignedInteger('platform_fee_cents')->default(0);
            $table->unsignedInteger('platform_fee_vat_cents')->default(0);
            $table->unsignedInteger('gateway_fee_cents')->default(0);
            $table->unsignedInteger('net_to_club_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('membership_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'platform_fee_cents',
                'platform_fee_vat_cents',
                'gateway_fee_cents',
                'net_to_club_cents',
            ]);
        });
    }
};
```

- [ ] **Step 5: Migrate + verify schema**

```bash
php artisan migrate
php artisan tinker --execute 'echo collect(Schema::getColumnListing("platform_subscriptions"))->implode(",");'
```

Expected: list includes all columns above.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*
git commit -m "Platform billing: migrations for organisations, platform_subscriptions, invoice fee columns"
```

---

### Task 4: `PlatformSubscription` model + factory

**Files:**
- Create: `app/Models/PlatformSubscription.php`
- Create: `database/factories/PlatformSubscriptionFactory.php`
- Create: `tests/Feature/PlatformSubscriptionModelTest.php`

**Interfaces:**
- Produces: `PlatformSubscription` with `organisation()` BelongsTo, `invoices()` HasMany (added Task 5).
- `PlatformSubscription::startTrial(Organisation, SubscriptionTier, BillingInterval): self` — factory constructor.
- `PlatformSubscription::isReadOnly(): bool` — delegates to status.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;

it('starts a 30-day trial for a brand-new club', function () {
    config()->set('platform.trial_days', 30);
    $org = Organisation::factory()->create();

    $sub = PlatformSubscription::startTrial($org, SubscriptionTier::Micro, BillingInterval::Monthly);

    expect($sub->organisation_id)->toBe($org->id)
        ->and($sub->tier)->toBe(SubscriptionTier::Micro)
        ->and($sub->status)->toBe(PlatformSubscriptionStatus::Trial)
        ->and($sub->trial_ends_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

it('reports read-only when past due or suspended', function () {
    $org = Organisation::factory()->create();
    $sub = PlatformSubscription::factory()->for($org)->create(['status' => PlatformSubscriptionStatus::PastDue]);

    expect($sub->isReadOnly())->toBeTrue();
});
```

- [ ] **Step 2: Watch it fail**

```bash
php artisan test tests/Feature/PlatformSubscriptionModelTest.php --compact
```

- [ ] **Step 3: Implement the model**

```php
<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organisation_id',
    'tier',
    'interval',
    'status',
    'paystack_authorization_code',
    'paystack_customer_code',
    'current_period_start',
    'current_period_end',
    'trial_ends_at',
    'cancel_at_period_end',
    'cancelled_at',
    'dunning_retry_count',
    'next_retry_on',
])]
class PlatformSubscription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tier' => SubscriptionTier::class,
            'interval' => BillingInterval::class,
            'status' => PlatformSubscriptionStatus::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'next_retry_on' => 'date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public static function startTrial(Organisation $organisation, SubscriptionTier $tier, BillingInterval $interval): self
    {
        return self::create([
            'organisation_id' => $organisation->id,
            'tier' => $tier,
            'interval' => $interval,
            'status' => PlatformSubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays((int) config('platform.trial_days', 30)),
        ]);
    }

    public function isReadOnly(): bool
    {
        return $this->status->isReadOnly();
    }
}
```

- [ ] **Step 4: Implement the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformSubscriptionFactory extends Factory
{
    protected $model = PlatformSubscription::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'tier' => SubscriptionTier::Micro,
            'interval' => BillingInterval::Monthly,
            'status' => PlatformSubscriptionStatus::Active,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ];
    }

    public function trial(): self
    {
        return $this->state(fn (): array => [
            'status' => PlatformSubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(30),
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
    }

    public function pastDue(): self
    {
        return $this->state(fn (): array => [
            'status' => PlatformSubscriptionStatus::PastDue,
            'next_retry_on' => now()->addDay(),
            'dunning_retry_count' => 1,
        ]);
    }
}
```

- [ ] **Step 5: Run tests, pass, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PlatformSubscriptionModelTest.php --compact
git add app/Models/PlatformSubscription.php database/factories/PlatformSubscriptionFactory.php tests/Feature/PlatformSubscriptionModelTest.php
git commit -m "Platform billing: PlatformSubscription model + factory with trial/pastDue states"
```

---

### Task 5: Platform Billing settings page

**NOTE (plan amendment):** This project does **not** have a single `SiteSettings` page with tabs. The pattern is:
- `App\Filament\Pages\Settings` is a landing page that lists child pages via `getSections()`.
- Each settings subject lives in its own `Manage<Subject>Settings` page (e.g. `ManageMailSettings`).
- Each subject has a `App\Support\<Subject>Settings` support class with static `details()`, `save()`, `apply()`, `keys()` methods.
- `apply()` is called from `AppServiceProvider::boot()` (not an inline method on the provider).
- Settings persist via `App\Models\Setting::put('key', $value)` / `::get('key', $default)`.

Follow this pattern for the Platform billing settings page. **Do not** introduce an inline `applyPlatformSettings()` method on `AppServiceProvider`; put it in a Support class and call it from `boot()`.

**Files:**
- Create: `app/Support/PlatformSettings.php` — Support class mirroring `App\Support\MailSettings`.
- Create: `app/Filament/Pages/ManagePlatformBillingSettings.php` — dedicated page mirroring `App\Filament\Pages\ManageMailSettings`.
- Create: `resources/views/filament/pages/manage-platform-billing-settings.blade.php` — mirror the mail-settings blade.
- Modify: `app/Filament/Pages/Settings.php` — add a `getSections()` entry linking to the new page.
- Modify: `app/Providers/AppServiceProvider.php` — call `PlatformSettings::apply()` from `boot()`.
- Create: `tests/Feature/PlatformBillingSettingsTest.php`

**Interfaces:**
- Produces: `settings` table keys `platform.fee_bps`, `platform.vat_bps`, `platform.payout_admin_fee_cents`, `platform.trial_days`, `platform.grace_window_days`, `platform.single_event_price_cents`. These override the `config/platform.php` defaults when present.
- `PlatformSettings::apply()` is called from `AppServiceProvider::boot()`.

- [ ] **Step 1: Read the existing Mailgun implementation before writing anything**

```bash
cat app/Support/MailSettings.php
cat app/Filament/Pages/ManageMailSettings.php
cat app/Filament/Pages/Settings.php
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\Setting;
use App\Support\PlatformSettings;

it('reads platform fee basis points from the settings table at runtime', function () {
    Setting::put('platform.fee_bps', '250');

    PlatformSettings::apply();

    expect(config('platform.fee_bps'))->toBe(250);
});

it('writes and applies all platform billing keys via save()', function () {
    PlatformSettings::save([
        'fee_bps' => 275,
        'vat_bps' => 1500,
        'payout_admin_fee_cents' => 5000,
        'trial_days' => 30,
        'grace_window_days' => 7,
        'single_event_price_cents' => 75000,
    ]);

    expect(config('platform.fee_bps'))->toBe(275)
        ->and(config('platform.vat_bps'))->toBe(1500)
        ->and(config('platform.payout_admin_fee_cents'))->toBe(5000);
});
```

- [ ] **Step 3: Implement `App\Support\PlatformSettings` (mirror MailSettings)**

```php
<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PlatformSettings
{
    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'fee_bps',
            'vat_bps',
            'payout_admin_fee_cents',
            'trial_days',
            'grace_window_days',
            'single_event_price_cents',
        ];
    }

    /** @return array<string, int|string|null> */
    public static function details(): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = Setting::get("platform.{$key}", (string) config("platform.{$key}"));
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data): void
    {
        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            Setting::put("platform.{$key}", (string) $data[$key]);
        }

        self::apply();
    }

    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        foreach (self::keys() as $key) {
            $value = Setting::get("platform.{$key}");

            if ($value === null || $value === '') {
                continue;
            }

            config(["platform.{$key}" => is_numeric($value) ? (int) $value : $value]);
        }
    }
}
```

- [ ] **Step 4: Register `PlatformSettings::apply()` in `AppServiceProvider::boot()`**

The existing `boot()` already calls `MailSettings::apply()` (or similar). Add alongside:

```php
public function boot(): void
{
    // existing code ...
    \App\Support\PlatformSettings::apply();
}
```

- [ ] **Step 5: Create `ManagePlatformBillingSettings` Filament page (mirror ManageMailSettings)**

The page lives at `app/Filament/Pages/ManagePlatformBillingSettings.php`, with `shouldRegisterNavigation(): false` and `canAccess(): auth()->user()?->is_staff`. `mount()` seeds the form from `PlatformSettings::details()`. `save()` calls `PlatformSettings::save($state)` and shows the standard success notification. Form schema:

```php
Section::make('Platform fees (transparent to members)')
    ->description('Changes apply immediately — no redeploy. Shown on every member receipt.')
    ->columns(2)
    ->schema([
        TextInput::make('fee_bps')
            ->label('Platform fee (basis points)')
            ->numeric()->minValue(0)->maxValue(10000)
            ->helperText('200 = 2%. Applied to the next transaction.'),
        TextInput::make('vat_bps')
            ->label('VAT rate (basis points)')
            ->numeric()->minValue(0)->maxValue(10000)
            ->helperText('1500 = 15%.'),
        TextInput::make('payout_admin_fee_cents')
            ->label('Payout admin fee (cents, excl. VAT)')
            ->numeric()->minValue(0)
            ->helperText('5000 = R50. Applied to the 2nd and later payouts per calendar month on the bank-transfer path.'),
    ]),
Section::make('Trial & grace')
    ->columns(2)
    ->schema([
        TextInput::make('trial_days')->numeric()->minValue(0)->maxValue(90),
        TextInput::make('grace_window_days')->numeric()->minValue(0)->maxValue(90),
    ]),
Section::make('Single-event product')
    ->schema([
        TextInput::make('single_event_price_cents')
            ->label('Price (cents, excl. VAT)')
            ->numeric()->minValue(0)
            ->helperText('75000 = R750.'),
    ]),
```

- [ ] **Step 6: Add the entry to `Settings::getSections()`**

```php
[
    'title' => 'Platform billing',
    'description' => 'Fee %, VAT %, trial length, grace window, single-event price.',
    'href' => ManagePlatformBillingSettings::getUrl(),
    'icon' => Heroicon::OutlinedCurrencyDollar,
],
```

- [ ] **Step 7: Blade view**

`resources/views/filament/pages/manage-platform-billing-settings.blade.php` mirrors `manage-mail-settings.blade.php` exactly — a `<x-filament-panels::page>` wrapping the form with a Save action.

- [ ] **Step 8: Run tests, pint, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PlatformBillingSettingsTest.php --compact
git add app/Support/PlatformSettings.php app/Filament/Pages/ManagePlatformBillingSettings.php resources/views/filament/pages/manage-platform-billing-settings.blade.php app/Filament/Pages/Settings.php app/Providers/AppServiceProvider.php tests/Feature/PlatformBillingSettingsTest.php
git commit -m "Platform billing: Settings page + Support class with runtime overrides for fee, VAT, trial, grace"
```

---

### Task 6: `PaystackPlatformPlanService` + Paystack-managed subscription signup

**NOTE (plan amendment):** The original Task 6 described a `charge_authorization` approach where the app manually bills a stored card each period. **Change of direction**: this project already uses Paystack-managed Plans + Subscriptions (`App\Services\Paystack\PaystackClient` has `createPlan`, `fetchSubscription`, `disableSubscription`, and `initializeTransaction` with a `plan` parameter that auto-creates a Paystack subscription). We align platform billing with that existing architecture:
- Define **6 plans** on Paystack upfront, one per `(tier, interval)` combination: micro-monthly, micro-annually, standard-monthly, standard-annually, large-monthly, large-annually.
- At trial-end (or user-driven upgrade), call `PaystackClient::initializeTransaction($email, $amountCents, metadata: [...], plan: $planCode)`. Paystack handles the first charge and creates a SUB_xxx subscription that auto-charges on the plan's cadence.
- All subsequent charges are driven by Paystack; our side listens for webhook events (Task 7) to update `PlatformSubscription.status` and record payments.
- No daily `BillRecurringSubscriptions` command. No `charge_authorization`.

**Files:**
- Create: `app/Services/Platform/PaystackPlatformPlanService.php` — exposes `getPlanCode(SubscriptionTier $tier, BillingInterval $interval): string`, `provisionPlans(): array` (seeds the 6 plans via `PaystackClient::createPlan`), `startSubscription(PlatformSubscription $sub, string $email, string $callbackUrl): string` (returns the Paystack authorize URL).
- Create: `app/Console/Commands/ProvisionPlatformBillingPlans.php` — one-shot setup command (`platform:provision-plans`) that provisions all 6 plans on Paystack and caches the plan codes.
- Add migration: `platform_billing_plans` table (`id`, `tier`, `interval`, `price_cents`, `paystack_plan_code`, timestamps) — serves as the cache of plan codes so we don't re-create plans on every deploy.
- Create: `app/Models/PlatformBillingPlan.php`
- Create: `tests/Feature/PaystackPlatformPlanServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\Paystack\PaystackClient`, `PlatformSubscription`, `SubscriptionTier`, `BillingInterval`.
- Produces:
  - `PaystackPlatformPlanService::getPlanCode(tier, interval): string` — reads from `platform_billing_plans`.
  - `PaystackPlatformPlanService::provisionPlans(): array` — idempotent; calls `PaystackClient::createPlan` once per combination and persists the returned plan_code.
  - `PaystackPlatformPlanService::startSubscription(sub, email, callbackUrl): string` — calls `PaystackClient::initializeTransaction` with `plan` = the right plan_code, stores the Paystack reference on `PlatformSubscription.paystack_reference`, returns the hosted authorize URL for a browser redirect.

- [ ] **Step 1: Read the existing Paystack client first**

```bash
cat app/Services/Paystack/PaystackClient.php
cat app/Console/Commands/PaystackSetupPlans.php  # existing event-side plan provisioning; mirror its style
```

- [ ] **Step 2: Migration + model for `platform_billing_plans`**

```php
Schema::create('platform_billing_plans', function (Blueprint $table): void {
    $table->id();
    $table->string('tier');                      // micro | standard | large
    $table->string('interval');                  // monthly | annual
    $table->unsignedInteger('price_cents');      // excl. VAT, excl. platform fee
    $table->string('paystack_plan_code')->nullable();
    $table->timestamps();
    $table->unique(['tier', 'interval']);
});
```

- [ ] **Step 3: Write the failing test (uses `Http::fake`)**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Models\PlatformBillingPlan;
use App\Services\Platform\PaystackPlatformPlanService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
});

it('provisions all 6 plans on Paystack and caches the plan codes', function () {
    Http::fake(function ($request) {
        $body = $request->data();
        $code = 'PLN_'.substr(sha1($body['name']), 0, 10);

        return Http::response([
            'status' => true,
            'message' => 'Plan created',
            'data' => [
                'plan_code' => $code,
                'name' => $body['name'],
                'interval' => $body['interval'],
                'amount' => $body['amount'],
            ],
        ], 200);
    });

    app(PaystackPlatformPlanService::class)->provisionPlans();

    expect(PlatformBillingPlan::count())->toBe(6);
    foreach (SubscriptionTier::cases() as $tier) {
        foreach (BillingInterval::cases() as $interval) {
            $plan = PlatformBillingPlan::query()
                ->where('tier', $tier->value)
                ->where('interval', $interval->value)
                ->firstOrFail();
            expect($plan->paystack_plan_code)->toStartWith('PLN_');
        }
    }
});

it('is idempotent — a second provisionPlans run makes no new HTTP calls', function () {
    PlatformBillingPlan::query()->insert([
        [
            'tier' => SubscriptionTier::Micro->value,
            'interval' => BillingInterval::Monthly->value,
            'price_cents' => SubscriptionTier::Micro->priceCents(BillingInterval::Monthly),
            'paystack_plan_code' => 'PLN_existing',
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    Http::fake(); // records calls
    app(PaystackPlatformPlanService::class)->provisionPlans();

    // Only the 5 missing combinations should trigger createPlan.
    Http::assertSentCount(5);
});

it('startSubscription returns the Paystack authorize URL and stores the reference', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'reference' => 'ref_abc',
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'ac_abc',
            ],
        ], 200),
    ]);

    PlatformBillingPlan::query()->insert([
        'tier' => SubscriptionTier::Micro->value,
        'interval' => BillingInterval::Monthly->value,
        'price_cents' => SubscriptionTier::Micro->priceCents(BillingInterval::Monthly),
        'paystack_plan_code' => 'PLN_test',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $sub = \App\Models\PlatformSubscription::factory()->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
    ]);

    $url = app(PaystackPlatformPlanService::class)
        ->startSubscription($sub, 'admin@example.test', 'https://app.test/billing/callback');

    expect($url)->toBe('https://checkout.paystack.com/abc');
    expect($sub->fresh()->paystack_reference)->toBe('ref_abc');
});
```

- [ ] **Step 4: Watch it fail, then implement**

```php
<?php

namespace App\Services\Platform;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Models\PlatformBillingPlan;
use App\Models\PlatformSubscription;
use App\Services\Paystack\PaystackClient;
use App\Support\PlatformFees;
use RuntimeException;

class PaystackPlatformPlanService
{
    public function __construct(private PaystackClient $client) {}

    public function getPlanCode(SubscriptionTier $tier, BillingInterval $interval): string
    {
        $plan = PlatformBillingPlan::query()
            ->where('tier', $tier->value)
            ->where('interval', $interval->value)
            ->firstOrFail();

        if (! $plan->paystack_plan_code) {
            throw new RuntimeException("Plan {$tier->value}/{$interval->value} not provisioned on Paystack. Run `php artisan platform:provision-plans`.");
        }

        return $plan->paystack_plan_code;
    }

    /** @return array<string, string> keyed by "tier.interval", values are plan_code */
    public function provisionPlans(): array
    {
        $provisioned = [];

        foreach (SubscriptionTier::cases() as $tier) {
            foreach (BillingInterval::cases() as $interval) {
                $priceExcl = $tier->priceCents($interval);
                $priceIncl = PlatformFees::vatInclusiveCents($priceExcl);
                $name = "ShootingSports {$tier->label()} ({$interval->label()})";

                $existing = PlatformBillingPlan::query()
                    ->where('tier', $tier->value)
                    ->where('interval', $interval->value)
                    ->first();

                if ($existing && $existing->paystack_plan_code) {
                    $provisioned["{$tier->value}.{$interval->value}"] = $existing->paystack_plan_code;
                    continue;
                }

                $result = $this->client->createPlan(
                    name: $name,
                    interval: $interval === BillingInterval::Annual ? 'annually' : 'monthly',
                    amountCents: $priceIncl,
                );

                PlatformBillingPlan::query()->updateOrCreate(
                    ['tier' => $tier->value, 'interval' => $interval->value],
                    ['price_cents' => $priceExcl, 'paystack_plan_code' => $result['plan_code']],
                );

                $provisioned["{$tier->value}.{$interval->value}"] = $result['plan_code'];
            }
        }

        return $provisioned;
    }

    public function startSubscription(PlatformSubscription $subscription, string $email, string $callbackUrl): string
    {
        $planCode = $this->getPlanCode($subscription->tier, $subscription->interval);
        $priceIncl = PlatformFees::vatInclusiveCents($subscription->tier->priceCents($subscription->interval));

        $result = $this->client->initializeTransaction(
            email: $email,
            amountCents: $priceIncl,
            metadata: [
                'purpose' => 'platform_subscription',
                'subscription_id' => $subscription->id,
                'organisation_id' => $subscription->organisation_id,
            ],
            callbackUrl: $callbackUrl,
            plan: $planCode,
        );

        $subscription->update(['paystack_reference' => $result['reference']]);

        return $result['authorization_url'];
    }
}
```

Note: `PaystackClient::initializeTransaction` already supports a `plan` parameter — verify the exact signature (`cat app/Services/Paystack/PaystackClient.php`) and adjust the call site to match it. If `plan` is not already a parameter, add it as an optional named arg at the end of `initializeTransaction` (the simplest and safest change).

- [ ] **Step 5: `platform:provision-plans` command**

```php
<?php

namespace App\Console\Commands;

use App\Services\Platform\PaystackPlatformPlanService;
use Illuminate\Console\Command;

class ProvisionPlatformBillingPlans extends Command
{
    protected $signature = 'platform:provision-plans';

    protected $description = 'Idempotently provision the 6 ShootingSports subscription plans on Paystack.';

    public function handle(PaystackPlatformPlanService $service): int
    {
        $provisioned = $service->provisionPlans();
        foreach ($provisioned as $key => $planCode) {
            $this->line("  {$key}: {$planCode}");
        }
        $this->info('Done.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Pint, run tests, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PaystackPlatformPlanServiceTest.php --compact
git add database/migrations app/Models/PlatformBillingPlan.php app/Services/Platform/PaystackPlatformPlanService.php app/Console/Commands/ProvisionPlatformBillingPlans.php tests/Feature/PaystackPlatformPlanServiceTest.php
git commit -m "Platform billing: PaystackPlatformPlanService + 6-plan provisioning command"
```

---

### Task 7: Paystack subscription webhook handler + lifecycle

**NOTE (plan amendment):** Original Task 7 described a daily `BillRecurringSubscriptions` command that iterated subscriptions and manually charged each one. **Not needed** — Paystack bills on the plan's cadence automatically. We only need to **listen** to Paystack webhooks and translate them into `PlatformSubscription` status transitions and payment records. Paystack also runs its own dunning retries on failed charges; our role is to reflect the final outcome.

**Files:**
- Create: `app/Http/Controllers/PlatformBillingWebhookController.php` — the HTTP endpoint Paystack posts to; verifies the HMAC signature, resolves `metadata.purpose === 'platform_subscription'`, dispatches to handler.
- Create: `app/Services/Platform/PlatformBillingWebhookHandler.php` — pure PHP, event-dispatch logic. Easy to test with array fixtures.
- Modify: `routes/web.php` — add the webhook route (`POST /webhooks/paystack/platform`).
- Create: `tests/Feature/PlatformBillingWebhookTest.php`

Paystack events we care about (per https://paystack.com/docs/payments/webhooks/):
| Event | Our action |
|---|---|
| `charge.success` (first charge, when metadata.purpose = platform_subscription) | Mark subscription Active, set `current_period_start/end`, record `PlatformPayment` row |
| `subscription.create` | Store `paystack_subscription_code` on `PlatformSubscription` |
| `subscription.not_renew` | User disabled renewal — status stays Active until period end, then becomes `Cancelled` |
| `subscription.disable` | Mark subscription Cancelled |
| `invoice.create` (upcoming invoice) | Log for admin visibility |
| `invoice.payment_failed` | Mark subscription PastDue (Paystack handles its own retry schedule) |
| `invoice.update` (success) | Mark subscription Active, extend `current_period_end`, record `PlatformPayment` |

**Interfaces:**
- Consumes: Paystack webhook JSON payload, `PlatformSubscription`.
- Produces: `PlatformSubscription.status` transitions, `PlatformPayment` records (one per successful charge).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\PlatformSubscription;
use App\Services\Platform\PlatformBillingWebhookHandler;

it('transitions trial → active on charge.success with a platform_subscription reference', function () {
    $sub = PlatformSubscription::factory()->create([
        'status' => PlatformSubscriptionStatus::Trial,
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
        'paystack_reference' => 'ref_abc',
    ]);

    app(PlatformBillingWebhookHandler::class)->handle([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_abc',
            'amount' => 11500,
            'metadata' => [
                'purpose' => 'platform_subscription',
                'subscription_id' => $sub->id,
            ],
        ],
    ]);

    $sub->refresh();
    expect($sub->status)->toBe(PlatformSubscriptionStatus::Active)
        ->and($sub->current_period_end)->not->toBeNull()
        ->and($sub->payments()->count())->toBe(1);
});

it('transitions active → past_due on invoice.payment_failed', function () {
    $sub = PlatformSubscription::factory()->create([
        'status' => PlatformSubscriptionStatus::Active,
        'paystack_subscription_code' => 'SUB_xyz',
    ]);

    app(PlatformBillingWebhookHandler::class)->handle([
        'event' => 'invoice.payment_failed',
        'data' => [
            'subscription' => ['subscription_code' => 'SUB_xyz'],
        ],
    ]);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::PastDue);
});

it('stores paystack_subscription_code on subscription.create', function () {
    $sub = PlatformSubscription::factory()->create(['paystack_reference' => 'ref_abc']);

    app(PlatformBillingWebhookHandler::class)->handle([
        'event' => 'subscription.create',
        'data' => [
            'subscription_code' => 'SUB_new',
            'metadata' => ['subscription_id' => $sub->id],
        ],
    ]);

    expect($sub->fresh()->paystack_subscription_code)->toBe('SUB_new');
});

it('rejects charge.success events for other purposes', function () {
    $sub = PlatformSubscription::factory()->create(['status' => PlatformSubscriptionStatus::Trial]);

    app(PlatformBillingWebhookHandler::class)->handle([
        'event' => 'charge.success',
        'data' => ['metadata' => ['purpose' => 'event_entry'], 'reference' => 'other'],
    ]);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::Trial);
});
```

- [ ] **Step 2: Implement `PlatformBillingWebhookHandler`**

Pure PHP handler, no HTTP. Dispatches by `event` field; each handler method is small and does one thing. Guards against charges not tagged `purpose === 'platform_subscription'`. Uses `paystack_reference` (first charge) or `paystack_subscription_code` (subsequent charges) to resolve the `PlatformSubscription`.

- [ ] **Step 3: `PlatformBillingWebhookController` (thin HTTP wrapper)**

```php
<?php

namespace App\Http\Controllers;

use App\Services\Platform\PlatformBillingWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformBillingWebhookController extends Controller
{
    public function __invoke(Request $request, PlatformBillingWebhookHandler $handler)
    {
        $signature = $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), config('services.paystack.secret_key'));

        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('Paystack webhook signature mismatch', ['ip' => $request->ip()]);
            return response('invalid signature', 400);
        }

        $handler->handle($request->all());

        return response('ok', 200);
    }
}
```

Route: `Route::post('/webhooks/paystack/platform', PlatformBillingWebhookController::class)->middleware(['throttle:60,1'])->withoutMiddleware(['web']);` — bypass CSRF via `withoutMiddleware`, or put the route in a group that doesn't include `VerifyCsrfToken`.

- [ ] **Step 4: `PlatformPayment` model + migration**

Simple ledger of successful Paystack charges tagged `purpose = platform_subscription`:

```php
Schema::create('platform_payments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('platform_subscription_id')->constrained()->cascadeOnDelete();
    $table->string('paystack_reference')->unique();
    $table->unsignedInteger('amount_cents');
    $table->string('currency', 3)->default('ZAR');
    $table->timestamp('paid_at');
    $table->timestamps();
});
```

- [ ] **Step 5: Pint, run tests, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PlatformBillingWebhookTest.php --compact
git add database/migrations app/Models/PlatformPayment.php app/Services/Platform/PlatformBillingWebhookHandler.php app/Http/Controllers/PlatformBillingWebhookController.php routes/web.php tests/Feature/PlatformBillingWebhookTest.php
git commit -m "Platform billing: Paystack webhook handler translates subscription lifecycle to our status machine"
```

---

### Task 8: Trial end notifier

**Files:**
- Create: `app/Console/Commands/NotifyTrialEnding.php`
- Create: `app/Mail/TrialEndingReminder.php`
- Create: `resources/views/mail/trial-ending-reminder.blade.php`
- Create: `tests/Feature/NotifyTrialEndingTest.php`

**Interfaces:**
- Consumes: `PlatformSubscription`.
- Produces: emails sent at T-5, T-1, T+0 for subscriptions whose status is `trial`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\PlatformSubscriptionStatus;
use App\Mail\TrialEndingReminder;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('mails trial reminders at T-5, T-1 and T+0', function () {
    Mail::fake();
    $org = Organisation::factory()->create();
    $admin = User::factory()->create();
    $org->users()->attach($admin->id, ['role' => 'admin', 'granted_at' => now()]);

    PlatformSubscription::factory()->for($org)->create([
        'status' => PlatformSubscriptionStatus::Trial,
        'trial_ends_at' => now()->addDays(5)->endOfDay(),
    ]);

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Mail::assertSent(TrialEndingReminder::class, 1);
});
```

- [ ] **Step 2: Implement**

Mailable with `trial_ends_at`, `tier`, `add_card_url` properties. Command queries trials with `trial_ends_at` matching `[+5, +1, 0]` day offsets and sends.

- [ ] **Step 3: Schedule, pint, test, commit**

```php
Schedule::command('subscriptions:notify-trial-ending')->dailyAt('09:00');
```

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/NotifyTrialEndingTest.php --compact
git add app/Console/Commands/NotifyTrialEnding.php app/Mail/TrialEndingReminder.php resources/views/mail/trial-ending-reminder.blade.php tests/Feature/NotifyTrialEndingTest.php routes/console.php
git commit -m "Platform billing: trial-ending reminders at T-5/T-1/T+0"
```

---

### Task 9: Agency Agreement + acceptance model

**Files:**
- Create: `database/migrations/<timestamp>_create_agency_agreement_acceptances_table.php`
- Create: `app/Models/AgencyAgreementAcceptance.php`
- Create: `database/factories/AgencyAgreementAcceptanceFactory.php`
- Create: `app/Services/Platform/AgencyAgreement.php` — returns the active version string + body.
- Create: `resources/views/agreements/collection-agent-agreement.blade.php` — the agreement body (TBD-pending-accountant copy flagged inline).
- Create: `tests/Feature/AgencyAgreementTest.php`

**Interfaces:**
- Produces:
  - `AgencyAgreement::currentVersion(): string` (e.g. `'2026-10-18'`).
  - `AgencyAgreement::body(): string` (Blade render).
  - `Organisation::hasSignedCurrentAgencyAgreement(): bool`.

- [ ] **Step 1: Migration**

```php
Schema::create('agency_agreement_acceptances', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('version');
    $table->string('ip_address')->nullable();
    $table->timestamps();
    $table->unique(['organisation_id', 'version']);
});
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\AgencyAgreementAcceptance;
use App\Models\Organisation;
use App\Services\Platform\AgencyAgreement;

it('reports a club as not having signed when no acceptance exists', function () {
    $org = Organisation::factory()->create();
    expect($org->hasSignedCurrentAgencyAgreement())->toBeFalse();
});

it('reports a club as signed once the current-version acceptance exists', function () {
    $org = Organisation::factory()->create();
    AgencyAgreementAcceptance::factory()->for($org)->create(['version' => AgencyAgreement::currentVersion()]);

    expect($org->fresh()->hasSignedCurrentAgencyAgreement())->toBeTrue();
});
```

- [ ] **Step 3: Implement the service, model, factory**

```php
<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\View;

class AgencyAgreement
{
    public static function currentVersion(): string
    {
        return (string) config('platform.agency_agreement_version', '2026-10-18');
    }

    public static function body(): string
    {
        return View::make('agreements.collection-agent-agreement', [
            'version' => self::currentVersion(),
        ])->render();
    }
}
```

Model + `hasSignedCurrentAgencyAgreement()` on `Organisation`:

```php
public function hasSignedCurrentAgencyAgreement(): bool
{
    return $this->agencyAgreementAcceptances()
        ->where('version', AgencyAgreement::currentVersion())
        ->exists();
}
```

Blade placeholder:

```blade
{{-- TBD: finalise this wording with the appointed accountant/lawyer before launch. --}}
<h1>Collection Agent Agreement (v {{ $version }})</h1>
<p>This agreement appoints shootingsports.co.za ("the Agent") as collection agent for ... </p>
<p>The Agent charges a platform fee of 2% + VAT on all gross collections ... </p>
...
```

- [ ] **Step 4: Pint, run tests, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/AgencyAgreementTest.php --compact
git add database/migrations/* app/Models/AgencyAgreementAcceptance.php database/factories/AgencyAgreementAcceptanceFactory.php app/Services/Platform/AgencyAgreement.php resources/views/agreements/collection-agent-agreement.blade.php tests/Feature/AgencyAgreementTest.php app/Models/Organisation.php
git commit -m "Platform billing: AgencyAgreement model + versioned acceptance + placeholder copy"
```

---

### Task 10: Paystack Subaccount wizard (Livewire)

**Files:**
- Create: `app/Livewire/Platform/EnablePaystackSubaccount.php`
- Create: `resources/views/livewire/platform/enable-paystack-subaccount.blade.php`
- Create: `app/Services/Platform/PaystackSubaccountService.php`
- Create: `tests/Feature/EnablePaystackSubaccountTest.php`

**Interfaces:**
- Consumes: `Organisation`, `AgencyAgreement`, Paystack HTTP client.
- Produces:
  - Livewire route `/desk/billing/paystack` mounts this component.
  - `PaystackSubaccountService::create(Organisation, array $bankDetails): string` — returns `subaccount_code` and updates `organisations.paystack_subaccount_id` + `_status`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Livewire\Platform\EnablePaystackSubaccount;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Platform\AgencyAgreement;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('refuses to create the subaccount until the agreement is accepted', function () {
    $org = Organisation::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id, ['role' => 'admin', 'granted_at' => now()]);
    $this->actingAs($user);

    Livewire::test(EnablePaystackSubaccount::class, ['organisation' => $org])
        ->set('settlement_bank', '058')
        ->set('account_number', '0123456789')
        ->set('business_name', 'Test Club')
        ->call('submit')
        ->assertHasErrors('agreement_accepted');
});

it('creates the subaccount via Paystack once agreement is accepted', function () {
    Http::fake([
        'api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => ['subaccount_code' => 'ACCT_test123', 'is_verified' => false],
        ], 201),
    ]);

    $org = Organisation::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id, ['role' => 'admin', 'granted_at' => now()]);
    $this->actingAs($user);

    Livewire::test(EnablePaystackSubaccount::class, ['organisation' => $org])
        ->set('agreement_accepted', true)
        ->set('settlement_bank', '058')
        ->set('account_number', '0123456789')
        ->set('business_name', 'Test Club')
        ->call('submit')
        ->assertHasNoErrors();

    $org->refresh();
    expect($org->paystack_subaccount_id)->toBe('ACCT_test123')
        ->and($org->paystack_subaccount_status)->toBe('pending')
        ->and($org->hasSignedCurrentAgencyAgreement())->toBeTrue();
});
```

- [ ] **Step 2: Implement the service + component**

Minimal Livewire component with properties `agreement_accepted`, `settlement_bank`, `account_number`, `business_name`. `submit()` validates agreement acceptance, writes `AgencyAgreementAcceptance`, calls Paystack `/subaccount`, stamps `paystack_subaccount_id` + status.

- [ ] **Step 3: Pint, test, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/EnablePaystackSubaccountTest.php --compact
git add app/Livewire/Platform/EnablePaystackSubaccount.php resources/views/livewire/platform/enable-paystack-subaccount.blade.php app/Services/Platform/PaystackSubaccountService.php tests/Feature/EnablePaystackSubaccountTest.php
git commit -m "Platform billing: Paystack subaccount enablement wizard (agreement + KYC)"
```

---

### Task 11: Member checkout breakdown + agent receipt

**Files:**
- Modify: `app/Http/Controllers/MembershipPaymentController.php` — attach subaccount + transaction_charge + metadata when org has a subaccount.
- Modify: `app/Http/Controllers/PaystackController.php` — on `charge.success` for `purpose = club_membership`, write `platform_fee_cents`, `platform_fee_vat_cents`, `gateway_fee_cents`, `net_to_club_cents` onto the matching `MembershipInvoice`.
- Create: `resources/views/receipts/membership-agent-receipt.blade.php`
- Modify: `resources/views/livewire/my-membership-details.blade.php` — show the three-way checkout breakdown.
- Create: `tests/Feature/CheckoutBreakdownTest.php`

**Interfaces:**
- Consumes: `PlatformFees`, `Organisation`, `MembershipInvoice`.
- Produces: checkout UI shows the three-line split; successful payment lands with fee columns populated; emailed receipt in club's name.

- [ ] **Step 1: Write the failing test for breakdown display**

```php
<?php

use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\User;

it('shows the three-line breakdown on the member checkout page', function () {
    config()->set('platform.fee_bps', 200);
    config()->set('platform.vat_bps', 1500);

    $user = User::factory()->create();
    $org = Organisation::factory()->create([
        'name' => 'Pretoria Rifle Club',
        'paystack_subaccount_id' => 'ACCT_abc',
    ]);
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $org->id,
        'user_id' => $user->id,
    ]);
    MembershipInvoice::factory()->for($member, 'clubMember')->for($org)->create([
        'amount_cents' => 60000,
        'status' => 'issued',
    ]);

    $this->actingAs($user)
        ->get(route('my-membership.show', $member))
        ->assertSee('Pretoria Rifle Club')
        ->assertSee('R600.00')          // fee to club
        ->assertSee('R13.80');          // platform fee incl VAT
});
```

- [ ] **Step 2: Implement checkout breakdown rendering**

Add to the view a conditional block that only renders when the member is viewing an issued invoice for a club with `paystack_subaccount_id`. Use `PlatformFees::splitFor()` to compute. Pass `platform_fee_cents` + `platform_fee_vat_cents` into the view.

- [ ] **Step 3: Update `MembershipPaymentController::paystack`**

```php
$split = PlatformFees::splitFor($invoice->amount_cents);
$txnCharge = $split['fee'] + $split['fee_vat'];

$payload = [
    'email' => $user->email,
    'amount' => $invoice->amount_cents,
    'metadata' => [
        'purpose' => 'club_membership',
        'invoice_id' => $invoice->id,
        'user_id' => $user->id,
    ],
];

if ($club->paystack_subaccount_id) {
    $payload['subaccount'] = $club->paystack_subaccount_id;
    $payload['bearer'] = 'subaccount';
    $payload['transaction_charge'] = $txnCharge;
}

$response = $paystack->initializeTransaction($payload);
```

- [ ] **Step 4: Update `PaystackController::onChargeSuccess` for the club_membership branch**

On success webhook: use the Paystack `fees` field in the webhook payload to populate `gateway_fee_cents`; compute `platform_fee_cents` + `platform_fee_vat_cents` from `PlatformFees::splitFor($invoice->amount_cents)`; compute `net_to_club_cents = gross - all three`.

- [ ] **Step 5: Agent-receipt Blade**

```blade
{{-- TBD: finalise wording with accountant/lawyer --}}
<h1>Receipt from {{ $organisation->name }}</h1>
<p>Collected on behalf of {{ $organisation->name }} by shootingsports.co.za (VAT no. {{ config('services.sars.vat_number') }}).</p>
<p>Membership fee: R{{ number_format($invoice->amount_cents / 100, 2) }}</p>
<p>Thank you.</p>
```

- [ ] **Step 6: Pint, test, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/CheckoutBreakdownTest.php --compact
git add app/Http/Controllers/MembershipPaymentController.php app/Http/Controllers/PaystackController.php resources/views/receipts/membership-agent-receipt.blade.php resources/views/livewire/my-membership-details.blade.php tests/Feature/CheckoutBreakdownTest.php
git commit -m "Platform billing: member checkout breakdown + agent receipt + fee column population on webhook"
```

---

### Task 12: `PlatformFeeStatement` + `CompileMonthlyFeeStatements` command

**Files:**
- Create: `database/migrations/<timestamp>_create_platform_fee_statements_table.php`
- Create: `database/migrations/<timestamp>_create_platform_fee_lines_table.php`
- Create: `app/Models/PlatformFeeStatement.php`
- Create: `app/Models/PlatformFeeLine.php`
- Create: `app/Services/Platform/CompileFeeStatement.php`
- Create: `app/Console/Commands/CompileMonthlyFeeStatements.php`
- Create: `tests/Feature/CompileMonthlyFeeStatementsTest.php`

**Interfaces:**
- Consumes: `MembershipInvoice` rows paid in the period.
- Produces: one `PlatformFeeStatement` per `(organisation, period_start, period_end)` with a `PlatformFeeLine` per paid invoice.

- [ ] **Step 1: Migrations**

```php
Schema::create('platform_fee_statements', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
    $table->date('period_start');
    $table->date('period_end');
    $table->unsignedInteger('total_fee_cents');
    $table->unsignedInteger('total_vat_cents');
    $table->unsignedInteger('total_gross_cents');
    $table->timestamp('issued_at')->nullable();
    $table->timestamps();
    $table->unique(['organisation_id', 'period_start', 'period_end']);
});

Schema::create('platform_fee_lines', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('platform_fee_statement_id')->constrained()->cascadeOnDelete();
    $table->foreignId('membership_invoice_id')->constrained();
    $table->unsignedInteger('gross_cents');
    $table->unsignedInteger('fee_cents');
    $table->unsignedInteger('fee_vat_cents');
    $table->timestamp('paid_at');
    $table->timestamps();
});
```

- [ ] **Step 2: Write the failing test, implement, pint, test, commit**

Standard pattern. The command loops `organisations.whereHas('paidInvoicesInPeriod')`, calls `CompileFeeStatement::for($org, $periodStart, $periodEnd)`, which is idempotent on `(org, period_start, period_end)`.

---

### Task 13: Platform subscription invoice + Blade tax-invoice template

**Files:**
- Create: `database/migrations/<timestamp>_create_platform_invoices_table.php`
- Create: `app/Models/PlatformInvoice.php`
- Modify: `BillRecurringSubscriptions` — write a `PlatformInvoice` row on successful charge.
- Create: `resources/views/invoices/platform-subscription-tax-invoice.blade.php`
- Create: `tests/Feature/PlatformInvoiceTest.php`

**Interfaces:**
- Produces: One `PlatformInvoice` per successful subscription charge, with inclusive + exclusive cents, VAT cents, Paystack reference, period dates.

Follow the Task 4 pattern. Blade template is a proper SA VAT tax invoice: our name, address, VAT number, client name/VAT, invoice number, line items, totals, VAT breakdown.

---

### Task 14: Desk Billing nav section

**Files:**
- Create: `app/Filament/Desk/Pages/BillingDashboard.php`
- Create: `resources/views/filament/desk/pages/billing-dashboard.blade.php`
- Create: `app/Filament/Desk/Pages/BillingPaystack.php` (wraps the EnablePaystackSubaccount livewire)
- Create: `app/Filament/Desk/Pages/BillingFeeStatements.php` (archive)
- Create: `tests/Feature/DeskBillingNavTest.php`

**Interfaces:**
- Produces: new nav group `Billing` on `/desk`, visible to users with `canManageOrganisation()`.

Each page is a standard Filament 5 page in nav group `Billing`. BillingDashboard shows current tier + usage bar + next billing date + change-tier / cancel buttons; FeeStatements shows the archive from Task 12.

---

### Task 15: Bank-transfer fallback — `ClubPayable` + payouts

**Files:**
- Create: `database/migrations/<timestamp>_create_club_payables_table.php`
- Create: `database/migrations/<timestamp>_create_platform_payouts_table.php`
- Create: `app/Models/ClubPayable.php`
- Create: `app/Models/PlatformPayout.php`
- Modify: `PaystackController::onChargeSuccess` — when the org has `payout_method = 'bank_transfer'`, write a `ClubPayable` row.
- Create: `app/Services/Platform/RequestPayout.php` — turns outstanding `ClubPayable` rows into a `PlatformPayout`, applying the admin fee from the 2nd payout per month.
- Create: `app/Console/Commands/BatchClubPayouts.php` — exports CSV of pending payouts for the owner's bank upload.
- Create: `tests/Feature/BankTransferPayoutsTest.php`

**Interfaces:**
- Produces: per-club settlement balance = sum of unpaid `ClubPayable.net_due_cents`; "Request payout" creates a `PlatformPayout`; batch command exports a CSV.

- [ ] **Step 1: Write the critical test — payout admin fee only applied on 2nd+ payout per calendar month**

```php
it('skips the payout admin fee for the first payout in a calendar month', function () {
    config()->set('platform.payout_admin_fee_cents', 5000);

    $org = Organisation::factory()->create();
    ClubPayable::factory()->for($org)->count(3)->create(['net_due_cents' => 10000, 'paid_out' => false]);

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->admin_fee_cents)->toBe(0)
        ->and($payout->net_cents)->toBe(30000);
});

it('deducts the payout admin fee from the 2nd payout in the same calendar month', function () {
    config()->set('platform.payout_admin_fee_cents', 5000);
    config()->set('platform.vat_bps', 1500);

    $org = Organisation::factory()->create();
    // First payout this month already exists.
    PlatformPayout::factory()->for($org)->create(['created_at' => now()->startOfMonth()->addDays(5)]);

    ClubPayable::factory()->for($org)->create(['net_due_cents' => 10000, 'paid_out' => false]);

    $payout = app(RequestPayout::class)->for($org);

    expect($payout->admin_fee_cents)->toBe(5000)
        ->and($payout->admin_fee_vat_cents)->toBe(750)
        ->and($payout->net_cents)->toBe(10000 - 5000 - 750);
});
```

- [ ] **Step 2-5: Standard implement/test/commit cycle**

---

### Task 16: Tier overage enforcement

**Files:**
- Create: `app/Console/Commands/EnforceTierOverages.php`
- Modify: `app/Filament/Desk/Pages/DeskDashboard.php` — show overage banner when `tier_grace_ends_at` is set.
- Create: `tests/Feature/EnforceTierOveragesTest.php`

**Interfaces:**
- Produces: nightly recompute of `organisations.active_member_count`; sets `tier_grace_ends_at = +30 days` when count crosses current tier cap; auto-upgrades on grace expiry.

- [ ] **Step 1: Write the failing test**

```php
it('stamps a 30-day grace window when active count crosses the Micro cap', function () {
    config()->set('platform.grace_window_days', 30);
    config()->set('platform.tier_member_caps.micro', 50);

    $org = Organisation::factory()->create(['active_member_count' => 0]);
    PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(51)->for($org, 'organisation')->active()->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    $org->refresh();
    expect($org->active_member_count)->toBe(51)
        ->and($org->tier_grace_ends_at->isSameDay(today()->addDays(30)))->toBeTrue();
});

it('auto-upgrades once the grace window expires', function () {
    $org = Organisation::factory()->create([
        'active_member_count' => 51,
        'tier_grace_ends_at' => today()->subDay(),
    ]);
    $sub = PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    expect($sub->fresh()->tier)->toBe(SubscriptionTier::Standard)
        ->and($org->fresh()->tier_grace_ends_at)->toBeNull();
});
```

- [ ] **Step 2-4: Implement, test, commit, schedule nightly 03:00**

---

### Task 17: Single-event product

**Files:**
- Create: `app/Http/Controllers/OrganiseController.php`
- Create: `resources/views/organise/signup.blade.php`
- Create a new migration — add `kind` string column to `organisations` with values `'club' | 'single_event'` and a default of `'club'` (**confirmed absent** as of 2026-10-18). Backfill all existing rows to `'club'`. Also add an `App\Enums\OrganisationKind` enum.
- Modify: Panel provider — hide Club nav when `organisation.kind = 'single_event'`.
- Create: `tests/Feature/SingleEventProductTest.php`

**Interfaces:**
- Produces: `/organise` public form creates user + `Organisation{kind: single_event}` + `Event` + `PlatformInvoice` for R862.50; Paystack one-off charge upfront; event stays `Draft` until payment clears.

Standard scaffold. Reuses existing Event model + the Phase-4 agent-receipt pattern.

---

### Task 18: Platform revenue admin dashboard

**Files:**
- Create: `app/Filament/Pages/PlatformRevenue.php`
- Create: `resources/views/filament/pages/platform-revenue.blade.php`

**Interfaces:**
- Produces: `/admin/platform-revenue` with tiles for MRR, ARR, trial→paid conversion (last 30 days), active subscriptions by tier, fee revenue (incl. VAT split), outstanding payables, failed charges.

Pure read-only aggregation. No tests needed beyond "page loads for staff; 403 for non-staff".

---

### Task 19: Playwright end-to-end suite

**Files:**
- Create: `tests/Playwright/platform-billing.spec.ts`
- Modify: `database/seeders/PlaywrightSeeder.php` — seed a club with an active subscription, a club in trial, a club with a `paystack_subaccount_id`, a club on `bank_transfer`, and a single-event organiser.

**Scenarios covered:**
1. Trial club admin sees trial banner on `/desk` with days-remaining counter.
2. Admin opens `/desk/billing/paystack`, cannot submit without agreement checkbox; after accepting + filling bank details, sees "pending" subaccount status.
3. Linked shooter visits `/my-memberships/{member}`, sees three-line checkout breakdown with correct cents.
4. Admin on bank-transfer club visits `/desk/billing/payouts`, sees settlement balance, clicks "Request payout", sees 1st-payout-free confirmation.
5. Admin changes tier from Micro to Standard, sees prorated preview.
6. Overage club shows banner on `/desk`.
7. `/organise` single-event signup flow end-to-end up to the Paystack checkout redirect.

Standard Playwright suite. Verify after each scenario the DB reflects what the UI claimed.

- [ ] **Final step: run everything green locally, pint clean, push**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npx playwright test tests/Playwright/platform-billing.spec.ts --reporter=list
git add -A
git commit -m "Platform billing: full Playwright end-to-end suite"
git push origin main
```

---

## Deployment notes

Standard shootingsports server rebuild — same path as all previous phases:

```bash
cd /opt/shootingsports && git pull origin main && docker compose build --no-cache app && docker compose up -d --force-recreate app scheduler queue
```

The `scheduler` container must be recreated because 5 new scheduled commands land in `routes/console.php` (`bill-recurring`, `retry-past-due`, `notify-trial-ending`, `enforce-tier-overages`, `batch-club-payouts`, `compile-monthly-fee-statements`).

After deploy, the three accountant/lawyer TBD placeholders must be reviewed and swapped before taking real money:

1. `resources/views/agreements/collection-agent-agreement.blade.php`
2. `resources/views/receipts/membership-agent-receipt.blade.php`
3. `resources/views/invoices/platform-subscription-tax-invoice.blade.php`

---

## Self-review notes

- **Spec coverage:** Each decision in the spec's "Decisions (locked)" table has a corresponding task. The Section 54 compliance section is covered by Tasks 9 (agreement) + 11 (agent receipt + split at source) + 12 (fee statement). Dunning schedule, trial, tier enforcement, single-event, bank-transfer fallback all have dedicated tasks.
- **Placeholder scan:** The three accountant TBD placeholders are intentional and flagged in both the plan and the spec. All other steps contain actual code or exact commands.
- **Type consistency:** `PlatformSubscription` properties referenced consistently across Tasks 4, 6, 7, 8, 13. `PlatformFees::splitFor()` return shape matches at every call site (Tasks 1, 11, 12, 15).

## Known holes to resolve during execution

Tracked in the spec's "Known holes" section. Chargeback / refund / partial-payment flows are deliberately not in this plan — they'll be handled in a follow-up spec once the main billing lane is live.
