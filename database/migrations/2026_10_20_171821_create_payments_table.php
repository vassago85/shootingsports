<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('event_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('paystack_subaccount_code');
            $table->char('currency', 3)->default('ZAR');
            $table->unsignedInteger('gross_amount_cents');
            $table->unsignedInteger('platform_fee_cents');
            $table->unsignedInteger('vat_on_fee_cents');
            $table->unsignedInteger('transaction_charge_cents');
            $table->unsignedInteger('paystack_fee_cents')->nullable();
            $table->unsignedInteger('club_share_cents')->nullable();
            $table->string('fee_bearer');
            $table->string('vat_treatment');
            $table->unsignedSmallInteger('vat_rate_basis_points');
            $table->string('status')->default('pending');
            $table->string('paystack_transaction_id')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('event_entry_id');
            $table->index(['organisation_id', 'created_at']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
