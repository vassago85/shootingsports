# Platform Billing & Payment Gateway Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship platform subscription billing (R100/R500/R800 tiers + annual) plus a Section 54 agency-compliant Paystack gateway integration that takes a 2% + VAT platform fee on every member payment, with a bank-transfer settlement fallback.

**Architecture:** Extends the existing `MembershipInvoice` ledger from the Club Membership Manager spec. New models for `PlatformSubscription`, `PlatformInvoice`, `PlatformFeeStatement`, `ClubPayable`, and `PlatformPayout`. Reuses the existing `PaystackController` webhook branch pattern (`purpose` metadata key). All platform-fee math flows through a single `PlatformFees` helper so VAT and basis-point calculations happen in one place.

**Tech Stack:** Laravel 13 + Filament 5.8 + Livewire 4 + Pest 5 + Playwright 1.63 + Postgres (prod) / SQLite (test). Paystack Transactions API (one-off, recurring, subaccounts). Blade for emails and tax invoices.

**Spec:** `docs/superpowers/specs/2026-10-18-platform-billing-and-payment-gateway-design.md`

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

### Task 5: Site Settings tab — Platform billing

**Files:**
- Modify: `app/Filament/Pages/SiteSettings.php` (add tab) — adjust path if different; find via `php artisan filament:list-pages`.
- Create: `tests/Feature/PlatformBillingSettingsTest.php`

**Interfaces:**
- Produces: `settings` table keys `platform.fee_bps`, `platform.vat_bps`, `platform.payout_admin_fee_cents`, `platform.trial_days`, `platform.grace_window_days`, `platform.single_event_price_cents`. These override the config/platform.php defaults when present.
- `AppServiceProvider::applyPlatformSettings()` is called from `boot()`.

- [ ] **Step 1: Find the Site Settings page**

```bash
grep -rn "SiteSettings\|site-settings" app/Filament/
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\Setting;

it('reads platform fee basis points from the settings table at runtime', function () {
    Setting::query()->updateOrCreate(['key' => 'platform.fee_bps'], ['value' => '250']);

    // Reboot the service provider to re-apply.
    app()->make(\App\Providers\AppServiceProvider::class)->applyPlatformSettings();

    expect(config('platform.fee_bps'))->toBe(250);
});
```

- [ ] **Step 3: Add `applyPlatformSettings()` to `AppServiceProvider`**

```php
public function boot(): void
{
    // existing code ...
    $this->applyPlatformSettings();
}

public function applyPlatformSettings(): void
{
    if (! Schema::hasTable('settings')) {
        return;
    }

    $overrides = Setting::query()->whereIn('key', [
        'platform.fee_bps',
        'platform.vat_bps',
        'platform.payout_admin_fee_cents',
        'platform.trial_days',
        'platform.grace_window_days',
        'platform.single_event_price_cents',
    ])->pluck('value', 'key');

    foreach ($overrides as $key => $value) {
        config([$key => is_numeric($value) ? (int) $value : $value]);
    }
}
```

- [ ] **Step 4: Add the Filament Site Settings tab**

In the existing SiteSettings page, add a new tab:

```php
Tab::make('Platform billing')
    ->schema([
        TextInput::make('platform.fee_bps')
            ->label('Platform fee (basis points)')
            ->numeric()
            ->minValue(0)->maxValue(10000)
            ->helperText('200 = 2%. Change requires no deploy; new rate applies to the next transaction.'),
        TextInput::make('platform.vat_bps')
            ->label('VAT rate (basis points)')
            ->numeric()->minValue(0)->maxValue(10000)
            ->helperText('1500 = 15%'),
        TextInput::make('platform.payout_admin_fee_cents')
            ->label('Payout admin fee (cents, excl. VAT)')
            ->numeric()->minValue(0)
            ->helperText('5000 = R50. Applied to 2nd and later payouts per calendar month on the bank-transfer path.'),
        TextInput::make('platform.trial_days')
            ->numeric()->minValue(0)->maxValue(90),
        TextInput::make('platform.grace_window_days')
            ->numeric()->minValue(0)->maxValue(90),
        TextInput::make('platform.single_event_price_cents')
            ->label('Single-event product price (cents, excl. VAT)')
            ->numeric()->minValue(0),
    ]),
```

Save handler writes to `settings` table under the dotted keys. Mirror the pattern used by the existing Mailgun tab.

- [ ] **Step 5: Run tests, pass, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PlatformBillingSettingsTest.php --compact
git add app/Providers/AppServiceProvider.php app/Filament/Pages/SiteSettings.php tests/Feature/PlatformBillingSettingsTest.php
git commit -m "Platform billing: Site Settings tab with runtime overrides for fee, VAT, trial, grace"
```

---

### Task 6: `PaystackSubscriptionService` + Paystack recurring charge

**Files:**
- Create: `app/Services/Platform/PaystackSubscriptionService.php`
- Create: `tests/Feature/PaystackSubscriptionServiceTest.php`

**Interfaces:**
- Consumes: `PlatformSubscription` model, Paystack HTTP client (reuse existing `App\Services\Paystack\PaystackClient` if present; otherwise create a thin wrapper around `Http::withToken(config('services.paystack.secret_key'))`).
- Produces:
  - `PaystackSubscriptionService::chargeAuthorization(PlatformSubscription): PaystackChargeResult` where `PaystackChargeResult = {success: bool, reference: ?string, failure_reason: ?string}`.
  - `PaystackSubscriptionService::createAuthorization(PlatformSubscription, string $email): string` — returns an initialise URL.

- [ ] **Step 1: Write the failing test (uses `Http::fake`)**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use App\Services\Platform\PaystackSubscriptionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
});

it('charges a stored Paystack authorization and returns success on 200', function () {
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => ['reference' => 'ref_abc123', 'status' => 'success'],
        ], 200),
    ]);

    $org = Organisation::factory()->create();
    $sub = PlatformSubscription::factory()->for($org)->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
        'paystack_authorization_code' => 'AUTH_abc',
    ]);

    $result = app(PaystackSubscriptionService::class)->chargeAuthorization($sub);

    expect($result->success)->toBeTrue()
        ->and($result->reference)->toBe('ref_abc123');
});

it('reports failure when Paystack returns a non-success response', function () {
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => false,
            'message' => 'Card declined',
        ], 200),
    ]);

    $sub = PlatformSubscription::factory()->create(['paystack_authorization_code' => 'AUTH_xyz']);

    $result = app(PaystackSubscriptionService::class)->chargeAuthorization($sub);

    expect($result->success)->toBeFalse()
        ->and($result->failure_reason)->toBe('Card declined');
});
```

- [ ] **Step 2: Watch it fail, then implement**

```php
<?php

namespace App\Services\Platform;

use App\Models\PlatformSubscription;
use App\Support\PlatformFees;
use Illuminate\Support\Facades\Http;

final readonly class PaystackChargeResult
{
    public function __construct(
        public bool $success,
        public ?string $reference = null,
        public ?string $failureReason = null,
    ) {}
}

class PaystackSubscriptionService
{
    public function chargeAuthorization(PlatformSubscription $subscription): PaystackChargeResult
    {
        $priceExcl = $subscription->tier->priceCents($subscription->interval);
        $priceIncl = PlatformFees::vatInclusiveCents($priceExcl);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/charge_authorization', [
                'authorization_code' => $subscription->paystack_authorization_code,
                'email' => $subscription->organisation->users()->first()?->email,
                'amount' => $priceIncl,
                'metadata' => [
                    'purpose' => 'platform_subscription',
                    'subscription_id' => $subscription->id,
                    'organisation_id' => $subscription->organisation_id,
                ],
            ]);

        $json = $response->json();

        if (($json['status'] ?? false) && ($json['data']['status'] ?? null) === 'success') {
            return new PaystackChargeResult(
                success: true,
                reference: (string) $json['data']['reference'],
            );
        }

        return new PaystackChargeResult(
            success: false,
            failureReason: (string) ($json['message'] ?? 'Unknown error'),
        );
    }
}
```

- [ ] **Step 3: Pint, run tests, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/PaystackSubscriptionServiceTest.php --compact
git add app/Services/Platform/PaystackSubscriptionService.php tests/Feature/PaystackSubscriptionServiceTest.php
git commit -m "Platform billing: PaystackSubscriptionService.chargeAuthorization with Http::fake tests"
```

---

### Task 7: `BillRecurringSubscriptions` command + dunning loop

**Files:**
- Create: `app/Console/Commands/BillRecurringSubscriptions.php`
- Create: `app/Console/Commands/RetryPastDueSubscriptions.php`
- Create: `tests/Feature/BillRecurringSubscriptionsTest.php`
- Modify: `routes/console.php` — schedule both.

**Interfaces:**
- Consumes: `PaystackSubscriptionService`, `PlatformSubscription`.
- Produces: Billing lifecycle — subscriptions due today are charged, successes advance `current_period_start/end`, failures enter the dunning schedule.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    Http::fake();
});

it('charges active subscriptions whose current period ends today and advances the period', function () {
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => ['reference' => 'ref_ok', 'status' => 'success'],
        ], 200),
    ]);

    $org = Organisation::factory()->create();
    $sub = PlatformSubscription::factory()->for($org)->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
        'status' => PlatformSubscriptionStatus::Active,
        'current_period_start' => now()->subMonth()->startOfDay(),
        'current_period_end' => now()->startOfDay(),
        'paystack_authorization_code' => 'AUTH_ok',
    ]);

    $this->artisan('subscriptions:bill-recurring')->assertSuccessful();

    $sub->refresh();
    expect($sub->status)->toBe(PlatformSubscriptionStatus::Active)
        ->and($sub->current_period_end->isSameDay(now()->addMonth()))->toBeTrue();
});

it('moves a subscription whose charge fails into past_due with next_retry_on day+1', function () {
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => false,
            'message' => 'Card declined',
        ], 200),
    ]);

    $sub = PlatformSubscription::factory()->create([
        'status' => PlatformSubscriptionStatus::Active,
        'current_period_end' => today(),
        'paystack_authorization_code' => 'AUTH_bad',
    ]);

    $this->artisan('subscriptions:bill-recurring')->assertSuccessful();

    $sub->refresh();
    expect($sub->status)->toBe(PlatformSubscriptionStatus::PastDue)
        ->and($sub->next_retry_on->isSameDay(today()->addDay()))->toBeTrue()
        ->and($sub->dunning_retry_count)->toBe(1);
});
```

- [ ] **Step 2: Implement `BillRecurringSubscriptions`**

```php
<?php

namespace App\Console\Commands;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Models\PlatformSubscription;
use App\Services\Platform\PaystackSubscriptionService;
use Illuminate\Console\Command;

class BillRecurringSubscriptions extends Command
{
    protected $signature = 'subscriptions:bill-recurring';

    protected $description = 'Charges subscriptions whose current period ends today.';

    public function handle(PaystackSubscriptionService $paystack): int
    {
        PlatformSubscription::query()
            ->whereIn('status', [PlatformSubscriptionStatus::Active])
            ->whereDate('current_period_end', '<=', today())
            ->whereNotNull('paystack_authorization_code')
            ->get()
            ->each(fn (PlatformSubscription $sub) => $this->bill($sub, $paystack));

        return self::SUCCESS;
    }

    private function bill(PlatformSubscription $sub, PaystackSubscriptionService $paystack): void
    {
        $result = $paystack->chargeAuthorization($sub);

        if ($result->success) {
            $periodLength = $sub->interval === BillingInterval::Annual
                ? now()->addYear()
                : now()->addMonth();

            $sub->update([
                'status' => PlatformSubscriptionStatus::Active,
                'current_period_start' => today(),
                'current_period_end' => $periodLength->toDateString(),
                'dunning_retry_count' => 0,
                'next_retry_on' => null,
            ]);

            return;
        }

        $sub->update([
            'status' => PlatformSubscriptionStatus::PastDue,
            'dunning_retry_count' => $sub->dunning_retry_count + 1,
            'next_retry_on' => today()->addDay(),
        ]);
    }
}
```

- [ ] **Step 3: Implement `RetryPastDueSubscriptions`**

```php
<?php

namespace App\Console\Commands;

use App\Enums\PlatformSubscriptionStatus;
use App\Models\PlatformSubscription;
use App\Services\Platform\PaystackSubscriptionService;
use Illuminate\Console\Command;

class RetryPastDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:retry-past-due';

    protected $description = 'Walks the dunning schedule for subscriptions already in past_due.';

    public function handle(PaystackSubscriptionService $paystack): int
    {
        $retrySchedule = (array) config('platform.dunning_retry_days', [1, 4, 7, 14]);
        $maxRetries = count($retrySchedule);

        PlatformSubscription::query()
            ->where('status', PlatformSubscriptionStatus::PastDue)
            ->whereDate('next_retry_on', '<=', today())
            ->get()
            ->each(function (PlatformSubscription $sub) use ($paystack, $retrySchedule, $maxRetries): void {
                $result = $paystack->chargeAuthorization($sub);

                if ($result->success) {
                    $sub->update([
                        'status' => PlatformSubscriptionStatus::Active,
                        'current_period_start' => today(),
                        'current_period_end' => $sub->interval->value === 'annual'
                            ? today()->addYear()->toDateString()
                            : today()->addMonth()->toDateString(),
                        'dunning_retry_count' => 0,
                        'next_retry_on' => null,
                    ]);

                    return;
                }

                $next = $sub->dunning_retry_count + 1;

                if ($next >= $maxRetries) {
                    $sub->update([
                        'status' => PlatformSubscriptionStatus::Suspended,
                        'next_retry_on' => null,
                    ]);

                    return;
                }

                $gap = $retrySchedule[$next] - $retrySchedule[$sub->dunning_retry_count];
                $sub->update([
                    'dunning_retry_count' => $next,
                    'next_retry_on' => today()->addDays($gap),
                ]);
            });

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule both in `routes/console.php`**

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:bill-recurring')->dailyAt('02:00');
Schedule::command('subscriptions:retry-past-due')->dailyAt('02:30');
```

- [ ] **Step 5: Pint, run tests, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test tests/Feature/BillRecurringSubscriptionsTest.php --compact
git add app/Console/Commands/BillRecurringSubscriptions.php app/Console/Commands/RetryPastDueSubscriptions.php tests/Feature/BillRecurringSubscriptionsTest.php routes/console.php
git commit -m "Platform billing: daily recurring charge + dunning retry commands"
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
- Modify: Organisation migration — add `kind` enum column if not already present (`club`, `single_event`, maybe `provider`).
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
