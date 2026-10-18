<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_payables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_invoice_id')->nullable()->unique()->constrained();
            $table->foreignId('platform_payout_id')->nullable()->constrained();
            $table->unsignedInteger('gross_cents');
            $table->unsignedInteger('platform_fee_cents');
            $table->unsignedInteger('platform_fee_vat_cents');
            $table->unsignedInteger('gateway_fee_cents');
            $table->unsignedInteger('net_due_cents');
            $table->boolean('paid_out')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['organisation_id', 'paid_out']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_payables');
    }
};
