<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('issued_on');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('description');
            $table->unsignedInteger('amount_excl_vat_cents');
            $table->unsignedInteger('vat_cents');
            $table->unsignedInteger('amount_incl_vat_cents');
            $table->unsignedSmallInteger('vat_bps');
            $table->string('currency', 3)->default('ZAR');
            $table->string('paystack_reference')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
