<?php

use Illuminate\Support\Facades\Schema;

it('creates platform_subscriptions table with the required columns', function () {
    expect(Schema::hasTable('platform_subscriptions'))->toBeTrue();

    foreach ([
        'organisation_id',
        'tier',
        'interval',
        'status',
        'paystack_plan_code',
        'paystack_subscription_code',
        'paystack_customer_code',
        'paystack_reference',
        'paystack_email_token',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancel_at_period_end',
        'cancelled_at',
        'dunning_retry_count',
        'next_retry_on',
    ] as $column) {
        expect(Schema::hasColumn('platform_subscriptions', $column))->toBeTrue("missing column: {$column}");
    }
});

it('extends organisations with platform billing columns', function () {
    foreach ([
        'paystack_subaccount_id',
        'paystack_subaccount_status',
        'payout_method',
        'agency_agreement_signed_at',
        'agency_agreement_version',
        'active_member_count',
        'tier_grace_ends_at',
    ] as $column) {
        expect(Schema::hasColumn('organisations', $column))->toBeTrue("missing column: {$column}");
    }
});

it('extends membership_invoices with fee breakdown columns', function () {
    foreach ([
        'platform_fee_cents',
        'platform_fee_vat_cents',
        'gateway_fee_cents',
        'net_to_club_cents',
    ] as $column) {
        expect(Schema::hasColumn('membership_invoices', $column))->toBeTrue("missing column: {$column}");
    }
});
