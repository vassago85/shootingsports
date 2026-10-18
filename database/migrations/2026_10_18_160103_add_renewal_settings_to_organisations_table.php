<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Club-level membership policy knobs. Calendar year by default
 * (31 Dec), reminder cadence at T-30/14/7, and an opt-in toggle for
 * online payments that mirrors the way events choose Paystack vs
 * External entry collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('membership_year_end_month')->default(12)->after('accepts_applications');
            $table->unsignedTinyInteger('membership_year_end_day')->default(31)->after('membership_year_end_month');
            $table->json('renewal_reminder_days')->nullable()->after('membership_year_end_day');
            $table->boolean('membership_online_payments')->default(false)->after('renewal_reminder_days');
            $table->text('membership_eft_instructions')->nullable()->after('membership_online_payments');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn([
                'membership_year_end_month',
                'membership_year_end_day',
                'renewal_reminder_days',
                'membership_online_payments',
                'membership_eft_instructions',
            ]);
        });
    }
};
