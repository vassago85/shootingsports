<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields Paystack needs to attach an account to a subscription:
 *
 *   paystack_customer_code       CUS_...  — created on first successful charge
 *   paystack_subscription_code   SUB_...  — the active subscription
 *   paystack_authorization_code  AUTH_... — the tokenised card, so we can
 *                                           create the subscription server-
 *                                           side after the first charge.
 *
 * Local state we keep independent of Paystack:
 *
 *   plan_billing_cycle           enum-ish: 'annual' | 'monthly' | null
 *                                (null = comp / manual / not subscribed)
 *   plan_cancelled_at            timestamp when the user hit "cancel". They
 *                                keep Pro until plan_expires_at; this is
 *                                just so the UI can say "cancelled, expires
 *                                on X" rather than looking active.
 *
 * plan_expires_at already exists — that's the single source of truth for
 * "when Pro runs out" across renewals, cancellations, comps and grace
 * periods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('paystack_customer_code')->nullable()->after('plan_expires_at');
            $table->string('paystack_subscription_code')->nullable()->after('paystack_customer_code');
            $table->string('paystack_authorization_code')->nullable()->after('paystack_subscription_code');
            $table->string('plan_billing_cycle')->nullable()->after('paystack_authorization_code');
            $table->timestamp('plan_cancelled_at')->nullable()->after('plan_billing_cycle');

            // Cheap index for the "list active subscribers" staff query.
            $table->index('paystack_subscription_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['paystack_subscription_code']);
            $table->dropColumn([
                'paystack_customer_code',
                'paystack_subscription_code',
                'paystack_authorization_code',
                'plan_billing_cycle',
                'plan_cancelled_at',
            ]);
        });
    }
};
