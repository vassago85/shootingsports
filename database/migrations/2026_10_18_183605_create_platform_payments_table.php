<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('paystack_reference')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
    }
};
