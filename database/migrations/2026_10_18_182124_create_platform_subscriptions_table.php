<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('paystack_plan_code')->nullable();
            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->string('paystack_email_token')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedTinyInteger('dunning_retry_count')->default(0);
            $table->date('next_retry_on')->nullable();
            $table->timestamps();

            $table->unique('organisation_id');
            $table->unique('paystack_subscription_code');
            $table->index(['status', 'current_period_end']);
            $table->index(['status', 'next_retry_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscriptions');
    }
};
