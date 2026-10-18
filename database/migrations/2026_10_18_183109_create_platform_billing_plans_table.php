<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('tier');
            $table->string('interval');
            $table->unsignedInteger('price_cents');
            $table->string('paystack_plan_code')->nullable();
            $table->timestamps();

            $table->unique(['tier', 'interval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_billing_plans');
    }
};
