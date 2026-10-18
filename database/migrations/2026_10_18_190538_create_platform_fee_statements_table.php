<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_fee_cents');
            $table->unsignedInteger('total_vat_cents');
            $table->unsignedInteger('total_gross_cents');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_statements');
    }
};
