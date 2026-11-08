<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('plan')->default('free');
            $table->timestamp('plan_expires_at')->nullable();
            $table->timestamp('plan_cancelled_at')->nullable();
            $table->string('plan_billing_cycle')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_authorization_code')->nullable();

            $table->index('plan');
            $table->index('paystack_subscription_code');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropIndex(['plan']);
            $table->dropIndex(['paystack_subscription_code']);
            $table->dropColumn([
                'plan',
                'plan_expires_at',
                'plan_cancelled_at',
                'plan_billing_cycle',
                'paystack_customer_code',
                'paystack_subscription_code',
                'paystack_authorization_code',
            ]);
        });
    }
};
