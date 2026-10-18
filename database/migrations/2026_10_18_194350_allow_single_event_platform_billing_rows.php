<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-event purchases have no subscription, and their invoice is
 * issued before the payment lands (the payment link is back-filled by
 * the webhook). Relax the three NOT NULL links accordingly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('platform_subscription_id')->nullable()->change();
        });

        Schema::table('platform_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('platform_subscription_id')->nullable()->change();
            $table->unsignedBigInteger('platform_payment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('platform_payment_id')->nullable(false)->change();
            $table->unsignedBigInteger('platform_subscription_id')->nullable(false)->change();
        });

        Schema::table('platform_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('platform_subscription_id')->nullable(false)->change();
        });
    }
};
