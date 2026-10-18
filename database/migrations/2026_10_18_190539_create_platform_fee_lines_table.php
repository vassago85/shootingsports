<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_fee_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_invoice_id')->constrained();
            $table->unsignedInteger('gross_cents');
            $table->unsignedInteger('fee_cents');
            $table->unsignedInteger('fee_vat_cents');
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['platform_fee_statement_id', 'membership_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_lines');
    }
};
